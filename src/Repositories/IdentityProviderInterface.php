<?php

namespace DalPraS\OpenId\Server\Repositories;

use League\OAuth2\Server\Repositories\RepositoryInterface;

interface IdentityProviderInterface extends RepositoryInterface
{
    public function getUserEntityByIdentifier($identifier);
}
