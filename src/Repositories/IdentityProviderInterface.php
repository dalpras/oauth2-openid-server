<?php

namespace DalPraS\OpenId\Server\Repositories;

use League\OAuth2\Server\Repositories\RepositoryInterface;

interface IdentityProviderInterface extends RepositoryInterface
{
    
    /**
     * Fetch a user by identifier
     *
     * @param mixed $identifier
     *
     * @return UserEntityInterface
     */
    public function getUserEntityByIdentifier($identifier);
}
