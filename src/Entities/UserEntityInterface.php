<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities;

use DalPraS\OpenId\Server\Entities\ClaimSetInterface;
use League\OAuth2\Server\Entities\UserEntityInterface as LeagueUserEntityInterface;

interface UserEntityInterface extends 
    LeagueUserEntityInterface, 
    ClaimSetInterface 
{}
