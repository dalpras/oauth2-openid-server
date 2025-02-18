<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities\Traits;

use DalPraS\OpenId\Server\ClaimExtractor;
use DalPraS\OpenId\Server\Entities\AccessTokenEntityInterface;
use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\ChainedFormatter;

trait BearerIdTokenTrait
{
    private Configuration $jwtConfiguration;

    private function convertToJWT()
    {
        /** @var \DalPraS\OpenId\Server\ResponseTypes\BearerIdTokenResponse $this */
        /** @var \DalPraS\OpenId\Server\Entities\AccessTokenEntityInterface $accessToken */
        $accessToken = $this->accessToken;

        /* @var \League\OAuth2\Server\Entities\UserEntityInterface $userEntity */
        $userEntity = $this->getIdentityProvider()->getUserEntityByIdentifier((string) $accessToken->getUserIdentifier());
                
        // Add required id_token claims
        /* @var $builder \Lcobucci\JWT\Token\Builder */
        $builder = $this->getJwtConfiguration()->builder(ChainedFormatter::withUnixTimestampDates())
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->issuedBy('https://' . $_SERVER['HTTP_HOST'])
            ->issuedAt(new DateTimeImmutable())
            ->expiresAt($accessToken->getExpiryDateTime())
            ->withHeader('kid', $accessToken::getKid())
            ->relatedTo((string) $userEntity->getIdentifier())
            ->withClaim('at_hash', $this->getAtHash($accessToken))
        ;
        
        // Need a claim factory here to reduce the number of claims by provided scope.
        $claims = $this->getClaimExtractor()->extract($accessToken->getScopes(), $userEntity->getClaims());    
        foreach ($claims as $name => $value) {
            $builder->withClaim($name, $value);
        }
        
        if ( $this->getNonce() !== null ) {
            $builder->withClaim('nonce', $this->getNonce());
        }
        $idtoken = $builder->getToken($this->getJwtConfiguration()->signer(), $this->getJwtConfiguration()->signingKey());
        return $idtoken;
    }

    private function getAtHash(AccessTokenEntityInterface $accessToken): string
    {
        // Hash the access token using SHA-256
        $hash = hash('sha256', $accessToken->__toString(), true);
        // Take the left-most half of the hash
        $halfHash = substr($hash, 0, strlen($hash) / 2);
        // Base64url encode the half hash
        $atHash = rtrim(strtr(base64_encode($halfHash), '+/', '-_'), '=');
        return $atHash;
    }    

    abstract public function getClaimExtractor(): ClaimExtractor;
    
    abstract public function getIdentityProvider(): IdentityProviderInterface;
    
    abstract public function getNonce(): ?string;

    abstract public function getJwtConfiguration(): Configuration;
}
