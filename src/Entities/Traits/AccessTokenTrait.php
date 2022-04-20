<?php
namespace DalPraS\OpenId\Server\Entities\Traits;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Lcobucci\JWT\Encoding\ChainedFormatter;

/**
 * This is the same of AccessTokenTrait of league/oauth2-server.
 * But the builder is modified for converting the dates to unixtimestamp without microseconds.
 * That's because some oauth clients convert the date from "long" integer values. (ie keycloak) 
 */
trait AccessTokenTrait
{
    /**
     * @var CryptKey
     */
    private $privateKey;

    /**
     * @var Configuration
     */
    private $jwtConfiguration;

    /**
     * Set the private key used to encrypt this access token.
     */
    public function setPrivateKey(CryptKey $privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * Initialise the JWT Configuration.
     */
    public function initJwtConfiguration()
    {
        $this->jwtConfiguration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->privateKey->getKeyContents(), $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('')
        );        
        // $this->jwtConfiguration = Configuration::forAsymmetricSigner(
        //     new Sha256(),
        //     InMemory::file($this->privateKey->getKeyPath(), $this->privateKey->getPassPhrase() ?? ''),
        //     InMemory::plainText('')
        // );
    }

    /**
     * Generate a JWT from the access token
     *
     * @return Token
     */
    private function convertToJWT()
    {
        $this->initJwtConfiguration();

        return $this->jwtConfiguration->builder(ChainedFormatter::withUnixTimestampDates())
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo((string) $this->getUserIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }

    /**
     * Generate a string representation from the access token
     */
    public function __toString()
    {
        return $this->convertToJWT()->toString();
    }

    /**
     * @return ClientEntityInterface
     */
    abstract public function getClient();

    /**
     * @return DateTimeImmutable
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

    /**
     * @return string
     */
    abstract public function getIdentifier();
}
