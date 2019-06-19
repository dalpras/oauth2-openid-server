<?php

namespace DalPraS\OpenId\Server\AuthorizationValidators;

use DalPraS\OpenId\Server\Repositories\AccessTokenProviderInterface;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

class BearerOpaqueTokenValidator implements AuthorizationValidatorInterface
{
    /**
     * @var AccessTokenProviderInterface
     */
    private $accessTokenRepository;
    
    /**
     * @param AccessTokenProviderInterface $accessTokenRepository
     */
    public function __construct(AccessTokenProviderInterface $accessTokenRepository)
    {
        $this->accessTokenRepository = $accessTokenRepository;
    }
    
    public function validateAuthorization(ServerRequestInterface $request)
    {
        if ($request->hasHeader('authorization') === false) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }
        
        $header = $request->getHeader('authorization');
        $jti = trim(preg_replace('/^(?:\s+)?Bearer\s/', '', $header[0]));

        try {
            // Check if token has been revoked
            if ($this->accessTokenRepository->isAccessTokenRevoked($jti)) {
                throw OAuthServerException::accessDenied('Access token has been revoked');
            }
    
            /* @var $accessToken \Vimar\OauthBundle\Model\AccessTokenEntity */
            $accessToken = $this->accessTokenRepository->getAccessTokenByIdentifier($jti);
            
            $client = $accessToken->getClient();
    
            // Return the request with additional attributes
            return $request
                    ->withAttribute('oauth_access_token_id', $jti)
                    ->withAttribute('oauth_client_id', $client->getIdentifier())
                    ->withAttribute('oauth_user_id', $accessToken->getUserIdentifier())
                    ->withAttribute('oauth_scopes', explode(' ', $client->getScope()) );

        } catch (\InvalidArgumentException $exception) {
            // JWT couldn't be parsed so return the request as is
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);

        } catch (\RuntimeException $exception) {
            //JWR couldn't be parsed so return the request as is
            throw OAuthServerException::accessDenied('Error while decoding to JSON', null, $exception);
        }
        
    }
    
}
