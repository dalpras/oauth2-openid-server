<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server\Entities;

interface ClaimSetEntityInterface extends ClaimSetInterface
{

    /**
     * @return string
     */
    public function getScope();
}
