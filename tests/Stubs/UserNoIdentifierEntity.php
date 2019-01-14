<?php

namespace DalPraS\OpenId\Server\Test\Stubs;

use DalPraS\OpenId\Server\Entities\ClaimSetInterface;

class UserNoIdentifierEntity implements ClaimSetInterface
{
    public function getClaims()
    {
        return [
            'first_name'    => 'Pluto',
            'last_name'     => 'Rotschield',
            'email'         => 'pluto.rot@example.com'
        ];
    }
}

