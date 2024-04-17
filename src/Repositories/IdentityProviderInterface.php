<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Repositories;

use League\OAuth2\Server\Repositories\RepositoryInterface;

interface IdentityProviderInterface extends RepositoryInterface
{
    
    /**
     * Fetch a user by identifier
     *
     * @param mixed $identifier
     *
     * @return \DalPraS\OpenId\Server\Entities\UserEntityInterface 
     */
    public function getUserEntityByIdentifier($identifier);
}
