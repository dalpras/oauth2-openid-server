<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Repositories;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface as LeagueAccessTokenRepositoryInterface;

/**
 * Access token interface.
 */
interface AccessTokenRepositoryInterface extends LeagueAccessTokenRepositoryInterface
{
    /**
     * Fetch an access token by identifier
     *
     * @param mixed $tokenId
     *
     * @return \DalPraS\OpenId\Server\Entities\AccessTokenEntityInterface
     */
    public function getAccessTokenByIdentifier($tokenId);
}
