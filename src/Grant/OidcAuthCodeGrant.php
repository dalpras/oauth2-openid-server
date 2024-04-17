<?php declare(strict_types=1);
/**
 * @author      Stefano Dal Prà
 * @copyright   Copyright (c) Stefano Dal Prà
 * @license     http://mit-license.org/
 * @link        https://github.com/dalpras/oauth2-openid-server
 */

namespace DalPraS\OpenId\Server\Grant;

use stdClass;
use Exception;
use DateInterval;
use LogicException;
use DateTimeImmutable;
use League\OAuth2\Server\RequestEvent;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use DalPraS\OpenId\Server\ResponseTypes\OidcResponse;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Grant\AbstractAuthorizeGrant;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\CodeChallengeVerifiers\S256Verifier;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use League\OAuth2\Server\CodeChallengeVerifiers\PlainVerifier;
use DalPraS\OpenId\Server\RequestTypes\OidcAuthorizationRequest;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * Using the code of \League\OAuth2\Server\Grant\AuthCodeGrant.
 * The code modify the behavior for dealing with "nonce" parameter needed in OIDC 
 */
class OidcAuthCodeGrant extends AbstractAuthorizeGrant
{
    /**
     * @var DateInterval
     */
    private $authCodeTTL;

    /**
     * @var bool
     */
    private $requireCodeChallengeForPublicClients = true;

    /**
     * @var CodeChallengeVerifierInterface[]
     */
    private $codeChallengeVerifiers = [];

    /**
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

        if (\in_array('sha256', \hash_algos(), true)) {
            $s256Verifier = new S256Verifier();
            $this->codeChallengeVerifiers[$s256Verifier->getMethod()] = $s256Verifier;
        }

        $plainVerifier = new PlainVerifier();
        $this->codeChallengeVerifiers[$plainVerifier->getMethod()] = $plainVerifier;
    }

    /**
     * Disable the requirement for a code challenge for public clients.
     */
    public function disableRequireCodeChallengeForPublicClients()
    {
        $this->requireCodeChallengeForPublicClients = false;
    }

    /**
     * Respond to an access token request.
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
        if (! $responseType instanceof OidcResponse) {
            throw OAuthServerException::invalidRequest('responseType');
        }
        list($clientId) = $this->getClientCredentials($request);

        $client = $this->getClientEntityOrFail($clientId, $request);

        // Only validate the client if it is confidential
        if ($client->isConfidential()) {
            $this->validateClient($request);
        }

        $encryptedAuthCode = $this->getRequestParameter('code', $request, null);

        if (!\is_string($encryptedAuthCode)) {
            throw OAuthServerException::invalidRequest('code');
        }

        try {
            $authCodePayload = \json_decode($this->decrypt($encryptedAuthCode));

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
        if (!empty($authCodePayload->code_challenge)) {
            $codeVerifier = $this->getRequestParameter('code_verifier', $request, null);

            if ($codeVerifier === null) {
                throw OAuthServerException::invalidRequest('code_verifier');
            }

            // Validate code_verifier according to RFC-7636
            // @see: https://tools.ietf.org/html/rfc7636#section-4.1
            if (\preg_match('/^[A-Za-z0-9-._~]{43,128}$/', $codeVerifier) !== 1) {
                throw OAuthServerException::invalidRequest(
                    'code_verifier',
                    'Code Verifier must follow the specifications of RFC-7636.'
                );
            }

            if (\property_exists($authCodePayload, 'code_challenge_method')) {
                if (isset($this->codeChallengeVerifiers[$authCodePayload->code_challenge_method])) {
                    $codeChallengeVerifier = $this->codeChallengeVerifiers[$authCodePayload->code_challenge_method];

                    if ($codeChallengeVerifier->verifyCodeChallenge($codeVerifier, $authCodePayload->code_challenge) === false) {
                        throw OAuthServerException::invalidGrant('Failed to verify `code_verifier`.');
                    }
                } else {
                    throw OAuthServerException::serverError(
                        \sprintf(
                            'Unsupported code challenge method `%s`',
                            $authCodePayload->code_challenge_method
                        )
                    );
                }
            }
        }

        /* @var $responseType \DalPraS\OpenId\Server\ResponseTypes\OidcResponse */
        // Issue and persist new access token
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $authCodePayload->user_id, $scopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);
        $responseType->setNonce($authCodePayload->nonce);

        // Issue and persist new refresh token if given
        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        // Revoke used auth code
        $this->authCodeRepository->revokeAuthCode($authCodePayload->auth_code_id);

        return $responseType;
    }

    /**
     * Validate the authorization code.
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
        if (!\property_exists($authCodePayload, 'auth_code_id')) {
            throw OAuthServerException::invalidRequest('code', 'Authorization code malformed');
        }

        if (\time() > $authCodePayload->expire_time) {
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
     * @return string
     */
    public function getIdentifier()
    {
        return 'authorization_code';
    }

    /**
     * {@inheritdoc}
     */
    public function canRespondToAuthorizationRequest(ServerRequestInterface $request)
    {
        return (
            \array_key_exists('response_type', $request->getQueryParams())
            && !empty(array_intersect(['code', 'id_token'], explode(' ', $request->getQueryParams()['response_type'])))
            && isset($request->getQueryParams()['client_id'])
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

        $client = $this->getClientEntityOrFail($clientId, $request);

        $redirectUri = $this->getQueryStringParameter('redirect_uri', $request);

        if ($redirectUri !== null) {
            if (!\is_string($redirectUri)) {
                throw OAuthServerException::invalidRequest('redirect_uri');
            }

            $this->validateRedirectUri($redirectUri, $client, $request);
        } elseif (empty($client->getRedirectUri()) ||
            (\is_array($client->getRedirectUri()) && \count($client->getRedirectUri()) !== 1)) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::CLIENT_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidClient($request);
        }

        $defaultClientRedirectUri = \is_array($client->getRedirectUri())
            ? $client->getRedirectUri()[0]
            : $client->getRedirectUri();

        $scopes = $this->validateScopes(
            $this->getQueryStringParameter('scope', $request, $this->defaultScope),
            $redirectUri ?? $defaultClientRedirectUri
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

        $codeChallenge = $this->getQueryStringParameter('code_challenge', $request);

        if ($codeChallenge !== null) {
            $codeChallengeMethod = $this->getQueryStringParameter('code_challenge_method', $request, 'plain');

            if (\array_key_exists($codeChallengeMethod, $this->codeChallengeVerifiers) === false) {
                throw OAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Code challenge method must be one of ' . \implode(', ', \array_map(
                        function ($method) {
                            return '`' . $method . '`';
                        },
                        \array_keys($this->codeChallengeVerifiers)
                    ))
                );
            }

            // Validate code_challenge according to RFC-7636
            // @see: https://tools.ietf.org/html/rfc7636#section-4.2
            if (\preg_match('/^[A-Za-z0-9-._~]{43,128}$/', $codeChallenge) !== 1) {
                throw OAuthServerException::invalidRequest(
                    'code_challenge',
                    'Code challenge must follow the specifications of RFC-7636.'
                );
            }

            $authorizationRequest->setCodeChallenge($codeChallenge);
            $authorizationRequest->setCodeChallengeMethod($codeChallengeMethod);
        } elseif ($this->requireCodeChallengeForPublicClients && !$client->isConfidential()) {
            throw OAuthServerException::invalidRequest('code_challenge', 'Code challenge must be provided for public clients');
        }

        return $authorizationRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function completeAuthorizationRequest(AuthorizationRequest $authorizationRequest)
    {
        if (! $authorizationRequest instanceof OidcAuthorizationRequest) {
            throw new LogicException('AuthorizationRequest have to be a valid instance of OidcAuthorizationRequest');
        }
        if (! $authorizationRequest->getUser() instanceof UserEntityInterface ) {
            throw new LogicException('An instance of UserEntityInterface should be set on the AuthorizationRequest');
        }

        /* @var \DalPraS\OpenId\Server\RequestTypes\OidcAuthorizationRequest $authorizationRequest */        
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
                'user_id'               => (string) $authCode->getUserIdentifier(),
                'expire_time'           => (new DateTimeImmutable())->add($this->authCodeTTL)->getTimestamp(),
                'code_challenge'        => $authorizationRequest->getCodeChallenge(),
                'code_challenge_method' => $authorizationRequest->getCodeChallengeMethod(),
                'nonce'                 => $authorizationRequest->getNonce()
            ];

            $jsonPayload = \json_encode($payload);

            if ($jsonPayload === false) {
                throw new LogicException('An error was encountered when JSON encoding the authorization request response');
            }

            $response = new RedirectResponse();
            $response->setRedirectUri(
                $this->makeRedirectUri(
                    $finalRedirectUri,
                    [
                        'code'  => $this->encrypt($jsonPayload),
                        'state' => $authorizationRequest->getState(),
                    ]
                )
            );

            return $response;
        }

        // The user denied the client, redirect them back with an error
        throw OAuthServerException::accessDenied(
            'The user denied the request',
            $this->makeRedirectUri(
                $finalRedirectUri,
                [
                    'state' => $authorizationRequest->getState(),
                ]
            )
        );
    }

    /**
     * Get the client redirect URI if not set in the request.
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
