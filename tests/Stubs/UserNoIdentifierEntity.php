<?php

namespace DalPraS\OpenId\Server\Test\Stubs;

use DalPraS\OpenId\Server\Entities\ClaimSetInterface;

class UserNoIdentifierEntity implements ClaimSetInterface
{
    public function getClaims()
    {
        return [
            'first_name'    => 'Steve',
            'last_name'     => 'Rhoades',
            'email'         => 'steve.rhoades@stephenrhoades.com'
        ];
    }
}

