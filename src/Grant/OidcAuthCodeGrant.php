<?php
namespace DalPraS\OpenId\Server\Grant;

use DalPraS\OpenId\Server\RequestTypes\OidcAuthorizationRequest;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractAuthorizeGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use DateInterval;
use DateTime;
use LogicException;

/**
 * This class override \League\OAuth2\Server\Grant\AuthCodeGrant
 * Cause many methods inside AuthCodeGrant are private, we need to copy
 * here the same methods and properties for having a custom suited class for Oidc.
 */
class OidcAuthCodeGrant extends AbstractAuthorizeGrant
{
    /**
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::$authCodeTTL
     * 
     * @var DateInterval
     */
    private $authCodeTTL;

    /**
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::$enableCodeExchangeProof
     * 
     * @var bool
     */
    private $enableCodeExchangeProof = false;

    /**
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::__construct()
     * 
     * @param AuthCodeRepositoryInterface     $authCodeRepository
     * @param RefreshTokenRepositoryInterface $refreshTokenRepository
     * @param DateInterval                    $authCodeTTL
     *
     * @throws Exception
     */
    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        DateInterval $authCodeTTL
    ) {
        $this->setAuthCodeRepository($authCodeRepository);
        $this->setRefreshTokenRepository($refreshTokenRepository);
        $this->authCodeTTL = $authCodeTTL;
        $this->refreshTokenTTL = new DateInterval('P1M');
    }

    /**
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::enableCodeExchangeProof()
     */
    public function enableCodeExchangeProof()
    {
        $this->enableCodeExchangeProof = true;
    }

    /**
     * Respond to an access token request.
     *
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::respondToAccessTokenRequest()
     *
     * @param ServerRequestInterface $request
     * @param ResponseTypeInterface  $responseType
     * @param DateInterval           $accessTokenTTL
     *
     * @throws OAuthServerException
     *
     * @return ResponseTypeInterface
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        // Validate request
        $client = $this->validateClient($request);
        $encryptedAuthCode = $this->getRequestParameter('code', $request, null);

        if ($encryptedAuthCode === null) {
            throw OAuthServerException::invalidRequest('code');
        }

        try {
            $authCodePayload = json_decode($this->decrypt($encryptedAuthCode));

            $this->validateAuthorizationCode($authCodePayload, $client, $request);

            $scopes = $this->scopeRepository->finalizeScopes(
                $this->validateScopes($authCodePayload->scopes),
                $this->getIdentifier(),
                $client,
                $authCodePayload->user_id
            );
        } catch (LogicException $e) {
            throw OAuthServerException::invalidRequest('code', 'Cannot decrypt the authorization code', $e);
        }

        // Validate code challenge
        if ($this->enableCodeExchangeProof === true) {
            $codeVerifier = $this->getRequestParameter('code_verifier', $request, null);

            if ($codeVerifier === null) {
                throw OAuthServerException::invalidRequest('code_verifier');
            }

            // Validate code_verifier according to RFC-7636
            // @see: https://tools.ietf.org/html/rfc7636#section-4.1
            if (preg_match('/^[A-Za-z0-9-._~]{43,128}$/', $codeVerifier) !== 1) {
                throw OAuthServerException::invalidRequest(
                    'code_verifier',
                    'Code Verifier must follow the specifications of RFC-7636.'
                );
            }

            switch ($authCodePayload->code_challenge_method) {
                case 'plain':
                    if (hash_equals($codeVerifier, $authCodePayload->code_challenge) === false) {
                        throw OAuthServerException::invalidGrant('Failed to verify `code_verifier`.');
                    }

                    break;
                case 'S256':
                    if (
                        hash_equals(
                            strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_'),
                            $authCodePayload->code_challenge
                        ) === false
                    ) {
                        throw OAuthServerException::invalidGrant('Failed to verify `code_verifier`.');
                    }
                    // @codeCoverageIgnoreStart
                    break;
                default:
                    throw OAuthServerException::serverError(
                        sprintf(
                            'Unsupported code challenge method `%s`',
                            $authCodePayload->code_challenge_method
                        )
                    );
                // @codeCoverageIgnoreEnd
            }
        }

        /* @var $responseType \DalPraS\OpenId\Server\ResponseTypes\OidcJwtResponse */
        
        // Issue and persist new access token
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $authCodePayload->user_id, $scopes);
        $this->getEmitter()->emit(new RequestEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request));
        $responseType->setAccessToken($accessToken);
        $responseType->setNonce($authCodePayload->nonce);

        // Issue and persist new refresh token if given
        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request));
            $responseType->setRefreshToken($refreshToken);
        }

        // Revoke used auth code "deleting"
        $this->authCodeRepository->revokeAuthCode($authCodePayload->auth_code_id);

        return $responseType;
    }

    /**
     * Validate the authorization code.
     *
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::validateAuthorizationCode()
     *
     * @param stdClass               $authCodePayload
     * @param ClientEntityInterface  $client
     * @param ServerRequestInterface $request
     */
    private function validateAuthorizationCode(
        $authCodePayload,
        ClientEntityInterface $client,
        ServerRequestInterface $request
    ) {
        if (time() > $authCodePayload->expire_time) {
            throw OAuthServerException::invalidRequest('code', 'Authorization code has expired');
        }

        if ($this->authCodeRepository->isAuthCodeRevoked($authCodePayload->auth_code_id) === true) {
            throw OAuthServerException::invalidRequest('code', 'Authorization code has been revoked');
        }

        if ($authCodePayload->client_id !== $client->getIdentifier()) {
            throw OAuthServerException::invalidRequest('code', 'Authorization code was not issued to this client');
        }

        // The redirect URI is required in this request
        $redirectUri = $this->getRequestParameter('redirect_uri', $request, null);
        if (empty($authCodePayload->redirect_uri) === false && $redirectUri === null) {
            throw OAuthServerException::invalidRequest('redirect_uri');
        }

        if ($authCodePayload->redirect_uri !== $redirectUri) {
            throw OAuthServerException::invalidRequest('redirect_uri', 'Invalid redirect URI');
        }
    }

    /**
     * Return the grant identifier that can be used in matching up requests.
     *
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::getIdentifier()
     *
     * @return string
     */
    public function getIdentifier()
    {
        return 'authorization_code';
    }

    /**
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::canRespondToAuthorizationRequest()
     * 
     * {@inheritdoc}
     */
    public function canRespondToAuthorizationRequest(ServerRequestInterface $request)
    {
        $queryParams = $request->getQueryParams();
        return (
            array_key_exists('response_type', $queryParams)
            && !empty(array_intersect(['code', 'id_token'], explode(' ', $queryParams['response_type']))) // $queryParams['response_type'] === 'code'
            && isset($queryParams['client_id'])
        );
    }

    /**
     * Create the authorization request "OidcAuthorizationRequest" with all the required
     * values from the QueryString.
     * 
     * Introduced "nonce" for compatibility with OIDC specs. 
     *  
     * The value of "state" enables the client to verify the validity of the request 
     * by matching the binding value to the user-agent's authenticated state. 
     * In short, it allows the client to cross check the authorization request and response.
     * 
     * "Nonce" serves a different purpose. 
     * It binds the tokens with the client. It serves as a token validation parameter.
     * The value is passed through unmodified from the Authentication Request to the ID Token. 
     * If present in the ID Token, Clients MUST verify that the nonce Claim Value is equal 
     * to the value of the nonce parameter sent in the Authentication Request. 
     * If present in the Authentication Request, Authorization Servers MUST include 
     * a "nonce" Claim in the ID Token with the Claim Value being the nonce value sent 
     * in the Authentication Request. 
     * Authorization Servers SHOULD perform no other processing on nonce values used. 
     * The nonce value is a case sensitive string.
     * 
     * {@inheritdoc}
     * 
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::validateAuthorizationRequest()
     */
    public function validateAuthorizationRequest(ServerRequestInterface $request)
    {
        $clientId = $this->getQueryStringParameter(
            'client_id',
            $request,
            $this->getServerParameter('PHP_AUTH_USER', $request)
        );

        if ($clientId === null) {
            throw OAuthServerException::invalidRequest('client_id');
        }

        $client = $this->clientRepository->getClientEntity(
            $clientId,
            $this->getIdentifier(),
            null,
            false
        );

        if ($client instanceof ClientEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));
            throw OAuthServerException::invalidClient();
        }

        $redirectUri = $this->getQueryStringParameter('redirect_uri', $request);

        if ($redirectUri !== null) {
            $this->validateRedirectUri($redirectUri, $client, $request);
        } elseif (empty($client->getRedirectUri()) ||
            (\is_array($client->getRedirectUri()) && \count($client->getRedirectUri()) !== 1)) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));
            throw OAuthServerException::invalidClient();
        } else {
            $redirectUri = \is_array($client->getRedirectUri())
                ? $client->getRedirectUri()[0]
                : $client->getRedirectUri();
        }

        $scopes = $this->validateScopes(
            $this->getQueryStringParameter('scope', $request, $this->defaultScope),
            $redirectUri
        );

        $stateParameter = $this->getQueryStringParameter('state', $request);
        
        $nonceParameter = $this->getQueryStringParameter('nonce', $request);
        
        $authorizationRequest = new OidcAuthorizationRequest();
        $authorizationRequest->setGrantTypeId($this->getIdentifier());
        $authorizationRequest->setClient($client);
        $authorizationRequest->setRedirectUri($redirectUri);

        if ($stateParameter !== null) {
            $authorizationRequest->setState($stateParameter);
        }
        
        if ($nonceParameter !== null) {
            $authorizationRequest->setNonce($nonceParameter);
        }

        $authorizationRequest->setScopes($scopes);

        if ($this->enableCodeExchangeProof === true) {
            $codeChallenge = $this->getQueryStringParameter('code_challenge', $request);
            if ($codeChallenge === null) {
                throw OAuthServerException::invalidRequest('code_challenge');
            }

            $codeChallengeMethod = $this->getQueryStringParameter('code_challenge_method', $request, 'plain');

            if (\in_array($codeChallengeMethod, ['plain', 'S256'], true) === false) {
                throw OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Code challenge method must be `plain` or `S256`'
                );
            }

            // Validate code_challenge according to RFC-7636
            // @see: https://tools.ietf.org/html/rfc7636#section-4.2
            if (preg_match('/^[A-Za-z0-9-._~]{43,128}$/', $codeChallenge) !== 1) {
                throw OAuthServerException::invalidRequest(
                    'code_challenged',
                    'Code challenge must follow the specifications of RFC-7636.'
                );
            }

            $authorizationRequest->setCodeChallenge($codeChallenge);
            $authorizationRequest->setCodeChallengeMethod($codeChallengeMethod);
        }

        return $authorizationRequest;
    }

    /**
     * Issue an OIDC auth code.
     *
     * @param DateInterval           $authCodeTTL
     * @param ClientEntityInterface  $client
     * @param string                 $userIdentifier
     * @param string|null            $redirectUri
     * @param ScopeEntityInterface[] $scopes
     *
     * @throws OAuthServerException
     * @throws UniqueTokenIdentifierConstraintViolationException
     *
     * @return AuthCodeEntityInterface
     */
//     private function issueOidcAuthCode(
//         DateInterval $authCodeTTL,
//         ClientEntityInterface $client,
//         $userIdentifier,
//         $redirectUri,
//         array $scopes = [] 
//     ) {
//         // $authCode is already persisted ...
//         $authCode = $this->issueAuthCode($authCodeTTL, $client, $userIdentifier, $redirectUri, $scopes);
        
//         // ... set extra properties and re-save current
        
//         // $this->authCodeRepository->persistNewAuthCode($authCode);
//         return $authCode;
//     }
    
    /**
     * Inherit authcode for managing openid parameter "nonce".
     * 
     * {@inheritdoc}
     * 
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::completeAuthorizationRequest()
     */
    public function completeAuthorizationRequest(AuthorizationRequest $authorizationRequest)
    {
        switch (false) {
            case $authorizationRequest instanceof OidcAuthorizationRequest:
                throw new LogicException('AuthorizationRequest have to be a valid instance of OidcAuthorizationRequest');

            case $authorizationRequest->getUser() instanceof UserEntityInterface:
                throw new LogicException('An instance of UserEntityInterface should be set on the AuthorizationRequest');
        }
        
        /* @var $authorizationRequest OidcAuthorizationRequest */
        
        $finalRedirectUri = $authorizationRequest->getRedirectUri()
                          ?? $this->getClientRedirectUri($authorizationRequest);

        // The user approved the client, redirect them back with an auth code
        if ($authorizationRequest->isAuthorizationApproved() === true) {
            $authCode = $this->issueAuthCode(
                $this->authCodeTTL,
                $authorizationRequest->getClient(),
                $authorizationRequest->getUser()->getIdentifier(),
                $authorizationRequest->getRedirectUri(),
                $authorizationRequest->getScopes()
            );

            $payload = [
                'client_id'             => $authCode->getClient()->getIdentifier(),
                'redirect_uri'          => $authCode->getRedirectUri(),
                'auth_code_id'          => $authCode->getIdentifier(),
                'scopes'                => $authCode->getScopes(),
                'user_id'               => $authCode->getUserIdentifier(),
                'expire_time'           => (new DateTime())->add($this->authCodeTTL)->format('U'),
                'code_challenge'        => $authorizationRequest->getCodeChallenge(),
                'code_challenge_method' => $authorizationRequest->getCodeChallengeMethod(),
                'nonce'                 => $authorizationRequest->getNonce()
            ];
            
            $response = new RedirectResponse();
            $params = [
                'code'  => $this->encrypt(json_encode($payload)),
                'state' => $authorizationRequest->getState(),
            ];
            $response->setRedirectUri(
                $this->makeRedirectUri($finalRedirectUri, $params)
            );

            return $response;
        }

        // The user denied the client, redirect them back with an error
        throw OAuthServerException::accessDenied(
            'The user denied the request',
            $this->makeRedirectUri($finalRedirectUri, ['state' => $authorizationRequest->getState()])
        );
    }

    /**
     * Get the client redirect URI if not set in the request.
     *
     * @see \League\OAuth2\Server\Grant\AuthCodeGrant::getClientRedirectUri()
     *
     * @param AuthorizationRequest $authorizationRequest
     *
     * @return string
     */
    private function getClientRedirectUri(AuthorizationRequest $authorizationRequest)
    {
        return \is_array($authorizationRequest->getClient()->getRedirectUri())
                ? $authorizationRequest->getClient()->getRedirectUri()[0]
                : $authorizationRequest->getClient()->getRedirectUri();
    }
}
