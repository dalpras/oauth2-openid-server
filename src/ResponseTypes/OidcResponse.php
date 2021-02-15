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

/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class OidcResponse extends BearerTokenResponse
{
    
    /**
     * @var Configuration
     */
    private Configuration $jwtConfiguration;
    
    /**
     * @var IdentityProviderInterface
     */
    private IdentityProviderInterface $identityProvider;

    /**
     * @var ClaimExtractor
     */
    private ClaimExtractor $claimExtractor;
    
    /**
     * @var string
     */
    private $nonce;

    /**
     * Get Custom Builder
     * 
     * @param AccessTokenEntityInterface $accessToken
     * @param UserEntityInterface $userEntity
     * @return \DalPraS\OpenId\Server\Jwt\Builder
     */
    private function getJwtBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity) {
        return $this->jwtConfiguration->builder()
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

        // Add required id_token claims
        /* @var $builder \Lcobucci\JWT\Token\Builder */
        $builder = $this->getJwtBuilder($accessToken, $userEntity);

        foreach ($claims as $name => $value) {
            $builder->withClaim($name, $value);
        }
        
        if ( $this->nonce ) {
            $builder->withClaim('nonce', $this->nonce);
        }

        $token = $builder->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
        
        return [
            'id_token' => $token->toString()
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
    
    /**
     * 
     * @param unknown $identityProvider
     * @return \DalPraS\OpenId\Server\ResponseTypes\OidcResponse
     */
    public function setIdentityProvider($identityProvider)
    {
        $this->identityProvider = $identityProvider;
        return $this;
    }

    /**
     * 
     * @param unknown $claimExtractor
     * @return \DalPraS\OpenId\Server\ResponseTypes\OidcResponse
     */
    public function setClaimExtractor($claimExtractor)
    {
        $this->claimExtractor = $claimExtractor;
        return $this;
    }

    /**
     * 
     * @param unknown $nonce
     * @return \DalPraS\OpenId\Server\ResponseTypes\OidcResponse
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
        return $this;
    }


    /**
     * @param unknown $jwtConfiguration
     * @return \DalPraS\OpenId\Server\ResponseTypes\OidcResponse
     */
    public function setJwtConfiguration($jwtConfiguration)
    {
        $this->jwtConfiguration = $jwtConfiguration;
        return $this;
    }

}
