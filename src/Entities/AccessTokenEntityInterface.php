<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface as LeagueAccessTokenEntityInterface;  

interface AccessTokenEntityInterface extends LeagueAccessTokenEntityInterface
{
    public static function getKid(): string;
}
