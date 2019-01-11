<?php

namespace DalPraS\OpenId\Server\Entities;

class ClaimSetEntity implements ClaimSetEntityInterface
{
    protected $scope;

    protected $claims;

    public function __construct($scope, array $claims)
    {
        $this->scope    = $scope;
        $this->claims   = $claims;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getClaims()
    {
        return $this->claims;
    }
}
