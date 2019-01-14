<?php

namespace DalPraS\OpenId\Server\Test\Stubs;

use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use DalPraS\OpenId\Server\Entities\ClaimSetInterface;

class UserEntity implements UserEntityInterface, ClaimSetInterface
{
    use EntityTrait;

    public function __construct()
    {
        $this->setIdentifier(123);
    }

    public function getClaims()
    {
        return [
            'first_name'    => 'Pluto',
            'last_name'     => 'Rotschield',
            'email'         => 'pluto.rot@example.com'
        ];
    }
}
