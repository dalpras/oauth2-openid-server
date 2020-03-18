<?php

namespace DalPraS\OpenId\Server\Repositories;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * Access token interface.
 */
interface AccessTokenProviderInterface extends AccessTokenRepositoryInterface
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
