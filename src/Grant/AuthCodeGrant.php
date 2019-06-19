<?php
namespace DalPraS\OpenId\Server\Grant;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Override for fixing method with php7.2
 * Cannot riding a null value as an array
 */
class AuthCodeGrant extends \League\OAuth2\Server\Grant\AuthCodeGrant {

    /**
     * Validate scopes in the request.
     *
     * @override for fixing
     *
     * @param string|array|\stdClass $scopes
     * @param string       $redirectUri
     *
     * @throws OAuthServerException
     *
     * @return ScopeEntityInterface[]
     */
    public function validateScopes($scopes, $redirectUri = null)
    {
        if (is_string($scopes)) {
            $scopes = $this->convertScopesQueryStringToArray($scopes);
        }

        $validScopes = [];

        foreach ( (array) $scopes as $scopeItem) {
            $scope = $this->scopeRepository->getScopeEntityByIdentifier($scopeItem);

            if ($scope instanceof ScopeEntityInterface === false) {
                throw OAuthServerException::invalidScope($scopeItem, $redirectUri);
            }

            $validScopes[] = $scope;
        }

        return $validScopes;
    }

    /**
     * Converts a scopes query string to an array to easily iterate for validation.
     *
     * @param string $scopes
     *
     * @return array
     */
    private function convertScopesQueryStringToArray($scopes)
    {
        return array_filter(explode(self::SCOPE_DELIMITER_STRING, trim($scopes)), function ($scope) {
            return !empty($scope);
        });
    }

}

