<?php

namespace DalPraS\OpenId\Server\Entities;


interface ClaimSetEntityInterface extends ClaimSetInterface {
    
    /**
     * @return string
     */
    public function getScope();
    
}
