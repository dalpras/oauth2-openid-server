<?php

namespace DalPraS\OpenId\Server\ResponseTypes;

use DalPraS\OpenId\Server\ClaimExtractor;
use DalPraS\OpenId\Server\Entities\ClaimSetInterface;
use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use DalPraS\OpenId\Server\Entities\UserEntityInterface;

/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class OidcJwtResponse extends BearerTokenResponse
{
    /**
     * @var IdentityProviderInterface
     */
    protected $identityProvider;

    /**
     * @var ClaimExtractor
     */
    protected $claimExtractor;
    
    /**
     * @var string
     */
    protected $nonce;
    
    public function __construct(IdentityProviderInterface $identityProvider, ClaimExtractor $claimExtractor) {
        $this->identityProvider = $identityProvider;
        $this->claimExtractor   = $claimExtractor;
    }
    
    /**
     * @return string
     */
    public function getNonce()
    {
        return $this->nonce;
    }

    /**
     * @param string $nonce
     */
    public function setNonce($nonce)
    {
        $this->nonce = $nonce;
    }

    /**
     * Get Lcobucci Builder
     * 
     * @param AccessTokenEntityInterface $accessToken
     * @param UserEntityInterface $userEntity
     * @return \Lcobucci\JWT\Builder
     */
    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity) {
        $builder = new Builder();
        // Add required id_token claims
        $builder->permittedFor($accessToken->getClient()->getIdentifier())
                ->issuedBy('https://' . $_SERVER['HTTP_HOST'])
                ->issuedAt(time())
                ->expiresAt($accessToken->getExpiryDateTime()->getTimestamp())
                ->relatedTo($userEntity->getIdentifier())
        ;
        return $builder;
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

        /** @var \League\OAuth2\Server\Entities\UserEntityInterface $userEntity */
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
        $builder = $this->getBuilder($accessToken, $userEntity);

        foreach ($claims as $claimName => $claimValue) {
            $builder->withClaim($claimName, $claimValue);
        }
        if ( ($nonce = $this->getNonce()) ) {
            $builder->withClaim('nonce', $nonce);
        }

        $token = $builder->getToken(new Sha256(), new Key($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase()));

        return [
            'id_token' => (string) $token
        ];
    }

    /**
     * Verify scope and make sure openid exists.
     *
     * @param ScopeEntityInterface[] $scopes
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