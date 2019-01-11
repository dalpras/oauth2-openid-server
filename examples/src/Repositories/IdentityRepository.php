<?php
namespace DalPraS\OpenId\Server\Examples\Repositories;

use DalPraS\OpenId\Server\Repositories\IdentityProviderInterface;
use DalPraS\OpenId\ServerExamples\Entities\UserEntity;

class IdentityRepository implements IdentityProviderInterface
{
    public function getUserEntityByIdentifier($identifier)
    {
        return new UserEntity();
    }
}
