<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\RequestTypes;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;

class OidcAuthorizationRequest extends AuthorizationRequest
{
    /**
     * The nonce parameter on the authorization request
     * 
     * It binds the tokens with the client. It serves as a token validation parameter.
     * The value is passed through unmodified from the Authentication Request to the ID Token. 
     * If present in the ID Token, Clients MUST verify that the nonce Claim Value is equal 
     * to the value of the nonce parameter sent in the Authentication Request. 
     * If present in the Authentication Request, Authorization Servers MUST include 
     * a "nonce" Claim in the ID Token with the Claim Value being the nonce value sent 
     * in the Authentication Request. 
     * Authorization Servers SHOULD perform no other processing on nonce values used. 
     * The nonce value is a case sensitive string.
     */
    protected ?string $nonce = null;
    
    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(?string $nonce): void
    {
        $this->nonce = $nonce;
    }
    
}
