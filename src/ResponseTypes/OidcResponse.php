<?php

namespace DalPraS\OpenId\Server\ResponseTypes;

use DalPraS\OpenId\Server\ClaimExtractor;
use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use DalPraS\OpenId\Server\Entities\UserEntityInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Key\InMemory;
use DalPraS\OpenId\Server\JwtBuilder;

/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class OidcJwtResponse extends BearerTokenResponse
{
    /**
     * @var IdentityProviderInterface
     */
    private $identityProvider;

    /**
     * @var ClaimExtractor
     */
    private $claimExtractor;
    
    /**
     * @var string
     */
    private $nonce;
    
    /**
     * @var Configuration
     */
    private $jwtConfiguration;
    
    public function __construct(IdentityProviderInterface $identityProvider, ClaimExtractor $claimExtractor) {
        $this->identityProvider = $identityProvider;
        $this->claimExtractor   = $claimExtractor;
    }
    
    /**
     * @param string $nonce
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
    }

    /**
     * Initialise the JWT Configuration.
     */
    public function getJwtConfiguration()
    {
        if ($this->jwtConfiguration === null) {
            $this->jwtConfiguration = Configuration::forAsymmetricSigner(
                new Sha256(),
                LocalFileReference::file($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase() ?? ''),
                InMemory::plainText('')
            );
            $this->jwtConfiguration->setBuilderFactory(static function() {
                return new \DalPraS\OpenId\Server\JwtBuilder();
            });
        }
        return $this->jwtConfiguration;
    }
    
    /**
     * Get Custom Builder
     * 
     * @param AccessTokenEntityInterface $accessToken
     * @param UserEntityInterface $userEntity
     * @return JwtBuilder
     */
    private function getJwtBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity) {
        return $this->getJwtConfiguration()->builder()
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->issuedBy('https://' . $_SERVER['HTTP_HOST'])
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt($accessToken->getExpiryDateTime())
            ->relatedTo((string) $userEntity->getIdentifier())
        ;
    }

    /**
     * @param AccessTokenEntityInterface $accessToken
     * @return array
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken)
    {
        if (false === $this->isOidcRequest($accessToken->getScopes())) {
            return [];
        }

        /* @var \League\OAuth2\Server\Entities\UserEntityInterface $userEntity */
        $userEntity = $this->identityProvider->getUserEntityByIdentifier($accessToken->getUserIdentifier());

        switch (false) {
            case is_a($userEntity, UserEntityInterface::class):
                throw new \RuntimeException('UserEntity must implement UserEntityInterface');
        }

        // Need a claim factory here to reduce the number of claims by provided scope.
        $claims = $this->claimExtractor->extract($accessToken->getScopes(), $userEntity->getClaims());

        // check 'sub' has a value
        if (empty($claims['sub'])) {
            throw new \RuntimeException('UserEntity must set the value of "sub" claim');
        }

        // Add required id_token claims
        $builder = $this->getJwtBuilder($accessToken, $userEntity);

        foreach ($claims as $name => $value) {
            $builder->setRegisteredClaim($name, $value, false);
        }
        
        if ( $this->nonce ) {
            $builder->setRegisteredClaim('nonce', $this->nonce, false);
        }

        $token = $builder->getToken($this->getJwtConfiguration()->signer(), $this->getJwtConfiguration()->signingKey());
        
        return [
            'id_token' => (string) $token
        ];
    }

    /**
     * Verify scope and make sure openid exists.
     *
     * @param \League\OAuth2\Server\Entities\ScopeEntityInterface[] $scopes
     * @return bool
     */
    private function isOidcRequest($scopes) {
        foreach ($scopes as $scope) {
            if ($scope->getIdentifier() === 'openid') {
                return true;
            }
        }
        return false;
    }

}
