<?php
namespace DalPraS\OpenId\Server;

use Lcobucci\JWT\Builder;

final class JwtBuilder extends Builder
{
    /**
     * Changing scope to public of setRegisteredClaim for easy claims definition
     * 
     * @see \Lcobucci\JWT\Builder::setRegisteredClaim()
     */
    public function setRegisteredClaim($name, $value, $replicate)
    {
        return parent::setRegisteredClaim($name, $value, $replicate);
    }

}