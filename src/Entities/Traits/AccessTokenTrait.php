<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities\Traits;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait as LeagueAccessTokenTrait;

trait AccessTokenTrait
{
    use LeagueAccessTokenTrait;

    /**
     * Generate a JWT from the access token
     *
     * @return Token
     */
    private function convertToJWT()
    {
        $this->initJwtConfiguration();

        return $this->jwtConfiguration->builder(ChainedFormatter::withUnixTimestampDates())
            ->permittedFor($this->getClient()->getIdentifier()) // Configures the audience (aud claim)
            ->identifiedBy($this->getIdentifier())        // Configures the id (jti claim), replicating as a header item
            ->issuedAt(new DateTimeImmutable())           // Configures the time that the token was issue (iat claim)
            ->canOnlyBeUsedAfter(new DateTimeImmutable()) // Configures the time that the token can be used (nbf claim)
            ->expiresAt($this->getExpiryDateTime())       // Configures the expiration time of the token (exp claim)
            // ->withHeader('kid', 'your-key-id')            // Configures a new header, called "kid"
            ->relatedTo((string) $this->getUserIdentifier())
            ->withClaim('scopes', $this->getScopes())
            ->getToken($this->jwtConfiguration->signer(), $this->jwtConfiguration->signingKey());
    }
}
