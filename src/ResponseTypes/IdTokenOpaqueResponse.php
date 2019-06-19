<?php

namespace DalPraS\OpenId\Server\ResponseTypes;

use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;


/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class IdTokenOpaqueResponse extends IdTokenJwtResponse
{

    /**
     * The id_token is a jwt, but access_token not.
     * We need to drop all information from the access_token to keep it compact.
     * 
     * {@inheritdoc}
     */
    public function generateHttpResponse(ResponseInterface $response)
    {
        $expireDateTime = $this->accessToken->getExpiryDateTime()->getTimestamp();
        
//         $jwtAccessToken = $this->accessToken->convertToJWT($this->privateKey);
        
        $responseParams = [
            'token_type'   => 'Bearer',
            'expires_in'   => $expireDateTime - (new \DateTime())->getTimestamp(),
            'access_token' => (string) $this->accessToken->getIdentifier(),
        ];
        
        if ($this->refreshToken instanceof RefreshTokenEntityInterface) {
//             $refreshToken = $this->encrypt(
//                 json_encode(
//                     [
//                         'client_id'        => $this->accessToken->getClient()->getIdentifier(),
//                         'refresh_token_id' => $this->refreshToken->getIdentifier(),
//                         'access_token_id'  => $this->accessToken->getIdentifier(),
//                         'scopes'           => $this->accessToken->getScopes(),
//                         'user_id'          => $this->accessToken->getUserIdentifier(),
//                         'expire_time'      => $this->refreshToken->getExpiryDateTime()->getTimestamp(),
//                     ]
//                     )
//                 );
            
            $responseParams['refresh_token'] = (string) $this->refreshToken->getIdentifier();
        }
        
        $responseParams = array_merge($this->getExtraParams($this->accessToken), $responseParams);
        
        $response = $response
                ->withStatus(200)
                ->withHeader('pragma', 'no-cache')
                ->withHeader('cache-control', 'no-store')
                ->withHeader('content-type', 'application/json; charset=UTF-8');
        
        $response->getBody()->write(json_encode($responseParams));
        
        return $response;
    }
    

}
