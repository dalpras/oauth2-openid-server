<?php

namespace DalPraS\OpenId\Server;

use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use DalPraS\OpenId\Server\Entities\ClaimSetInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Builder;

/**
 * Extends the BearerTokenResponse for adding
 * the param tokenId needed in OpenId.
 */
class IdTokenResponse extends BearerTokenResponse
{
    /**
     * @var IdentityProviderInterface
     */
    protected $identityProvider;

    /**
     * @var ClaimExtractor
     */
    protected $claimExtractor;

    public function __construct(IdentityProviderInterface $identityProvider, ClaimExtractor $claimExtractor) {
        $this->identityProvider = $identityProvider;
        $this->claimExtractor   = $claimExtractor;
    }

    protected function getBuilder(AccessTokenEntityInterface $accessToken, UserEntityInterface $userEntity)
    {
        // Add required id_token claims
        $builder = (new Builder())
            ->setAudience($accessToken->getClient()->getIdentifier())
            ->setIssuer('https://' . $_SERVER['HTTP_HOST'])
            ->setIssuedAt(time())
            ->setExpiration($accessToken->getExpiryDateTime()->getTimestamp())
            ->setSubject($userEntity->getIdentifier());

        return $builder;
    }

    /**
     * @param AccessTokenEntityInterface $accessToken
     * @return array
     */
    protected function getExtraParams(AccessTokenEntityInterface $accessToken)
    {
        if (false === $this->isOpenIDRequest($accessToken->getScopes())) {
            return [];
        }

        /** @var UserEntityInterface $userEntity */
        $userEntity = $this->identityProvider->getUserEntityByIdentifier($accessToken->getUserIdentifier());

        if (false === is_a($userEntity, UserEntityInterface::class)) {
            throw new \RuntimeException('UserEntity must implement UserEntityInterface');
        } else if (false === is_a($userEntity, ClaimSetInterface::class)) {
            throw new \RuntimeException('UserEntity must implement ClaimSetInterface');
        }

        // Add required id_token claims
        $builder = $this->getBuilder($accessToken, $userEntity);

        // Need a claim factory here to reduce the number of claims by provided scope.
        $claims = $this->claimExtractor->extract($accessToken->getScopes(), $userEntity->getClaims());

        // check 'sub' has a value
        if (empty($claims['sub'])) {
            throw new \RuntimeException('UserEntity must set the value of "sub" claim');
        }

        foreach ($claims as $claimName => $claimValue) {
            $builder->set($claimName, $claimValue);
        }

        $token = $builder
            ->sign(new Sha256(), new Key($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase()))
            ->getToken();

        return [
            'id_token' => (string) $token
        ];
    }

    /**
     * @param ScopeEntityInterface[] $scopes
     * @return bool
     */
    private function isOpenIDRequest($scopes) {
        // Verify scope and make sure openid exists.
        $valid  = false;

        foreach ($scopes as $scope) {
            if ($scope->getIdentifier() === 'openid') {
                $valid = true;
                break;
            }
        }

        return $valid;
    }




}
