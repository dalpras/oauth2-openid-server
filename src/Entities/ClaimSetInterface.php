<?php

namespace DalPraS\OpenId\Server\Entities;

interface ClaimSetInterface
{
    /**
     * @return array
     */
    public function getClaims();
}
