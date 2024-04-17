<?php declare(strict_types=1);

namespace DalPraS\OpenId\Server;

use ArrayObject;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Contains custom and standard ClaimSets.
 *
 * It's possible to add a custom ClaimSet and "extract" using defined scopes.
 * It's not possible to add a custom ClaimSet already present as a standard ClaimSet.
 */
class ClaimExtractor extends ArrayObject 
{
    /**
     * For given scopes and aggregated claims get all claims that have been configured on the extractor.
     * 
     * @param array $userScopes
     * @param array $userClaims
     * @return array|mixed
     */
    public function extract(array $userScopes, array $userClaims) {
        $claims = array_reduce($this->getArrayCopy(), function ($carry, $claimSet) use (&$userScopes, $userClaims) {
            /* @var $scope \League\OAuth2\Server\Entities\ScopeEntityInterface */
            foreach ($userScopes as $key => $userScope) {
                $scopeName = ($userScope instanceof ScopeEntityInterface) ? $userScope->getIdentifier() : $userScope;
                /* @var $claimSet \DalPraS\OpenId\Server\Entities\ClaimSetEntityInterface */
                if ( $scopeName === $claimSet->getScope() ) {
                    // if the userScope was matched
                    unset($userScopes[$key]);
                    $data = array_intersect_key($userClaims, array_fill_keys($claimSet->getClaims(), 1));
                    $carry = array_replace($carry, $data);
                    return $carry;
                }
            }
            return $carry;
        }, []);
        
        // if all userScopes were statisfied, return the claims collected, instead return nothing
        return empty($userScopes) ? $claims : [];
    }
    
}
