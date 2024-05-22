<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\ResponseTypes;

use DalPraS\OpenId\Server\ClaimExtractor;
use DalPraS\OpenId\Server\Entities\Traits\BearerIdTokenTrait;
use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class BearerIdTokenResponse extends BearerTokenResponse
{
    use BearerIdTokenTrait;

    private ?string $nonce = null;

    public function __construct(
        private Configuration $jwtConfiguration, 
        private ClaimExtractor $claimExtractor,
        private IdentityProviderInterface $identityProvider
    ) {
        // $privateKey = new CryptKey($jwtConfiguration->signingKey()->contents());
        // $this->setPrivateKey($privateKey);
    }
    
    /**
     * @return array
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken)
    {
        if (false === $this->isOidcRequest($this->accessToken->getScopes())) {
            return [];
        }
        
        return [
            'id_token' => $this->convertToJWT()->toString()
        ];        
    }

    /**
     * Verify scope and make sure openid exists.
     *
     * @param \League\OAuth2\Server\Entities\ScopeEntityInterface[] $scopes
     */
    private function isOidcRequest($scopes): bool 
    {
        foreach ($scopes as $scope) {
            if ($scope->getIdentifier() === 'openid') {
                return true;
            }
        }
        return false;
    }
    
    public function getClaimExtractor(): ClaimExtractor
    {
        return $this->claimExtractor;
    }

    public function getJwtConfiguration(): Configuration
    {
        return $this->jwtConfiguration;
    }

    public function getIdentityProvider(): IdentityProviderInterface
    {
        return $this->identityProvider;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(?string $nonce): self
    {
        $this->nonce = $nonce;
        return $this;
    }
}
