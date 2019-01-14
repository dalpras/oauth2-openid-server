<?php

namespace DalPraS\OpenId\Server\Entities;

/**
 * Container a list of claims for a defined scope.
 */
class ClaimSetEntity implements ClaimSetEntityInterface
{
    /**
     * @var string
     */
    protected $scope;

    /**
     * @var array
     */
    protected $claims;

    public function __construct($scope, array $claims) {
        $this->scope    = $scope;
        $this->claims   = $claims;
    }

    /**
     * @return string
     * @see \DalPraS\OpenId\Server\Entities\ScopeInterface::getScope()
     */
    public function getScope() {
        return $this->scope;
    }

    /**
     * @return string
     * @see \DalPraS\OpenId\Server\Entities\ClaimSetInterface::getClaims()
     */
    public function getClaims() {
        return $this->claims;
    }
}
