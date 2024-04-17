<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities;

interface ClaimSetInterface
{
    /**
     * @return array
     */
    public function getClaims();
}
