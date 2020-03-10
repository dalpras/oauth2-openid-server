<?php

namespace DalPraS\OpenId\Server\Entities;

interface UserEntityInterface extends 
    \League\OAuth2\Server\Entities\UserEntityInterface, 
    \DalPraS\OpenId\Server\Entities\ClaimSetInterface {

}
