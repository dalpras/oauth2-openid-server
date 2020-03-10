<?php
namespace DalPraS\OpenId\Server\Entities\Traits;

use DateTime;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

trait AccessTokenTrait
{
    /**
     * Generate a JWT from the access token
     *
     * @param CryptKey $privateKey
     *
     * @return Token
     */
    public function convertToJWT(CryptKey $privateKey)
    {
        $builder = new Builder();
        return $builder
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier(), true)
            ->issuedAt(time())
            ->canOnlyBeUsedAfter(time())
            ->expiresAt($this->getExpiryDateTime()->getTimestamp())
            ->relatedTo($this->getUserIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->getToken(new Sha256(), new Key($privateKey->getKeyPath(), $privateKey->getPassPhrase()));
    }

    /**
     * @return ClientEntityInterface
     */
    abstract public function getClient();

    /**
     * @return DateTime
     */
    abstract public function getExpiryDateTime();

    /**
     * @return string|int
     */
    abstract public function getUserIdentifier();

    /**
     * @return ScopeEntityInterface[]
     */
    abstract public function getScopes();
}
