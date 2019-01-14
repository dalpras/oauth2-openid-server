# OAuth 2.0 OpenID Server

[![Build Status](https://travis-ci.org/dalpras/oauth2-openid-server.svg?branch=master)](https://travis-ci.org/dalpras/oauth2-openid-server) [![Code Coverage](https://scrutinizer-ci.com/g/dalpras/oauth2-openid-server/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dalpras/oauth2-openid-server/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/dalpras/oauth2-openid-server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/dalpras/oauth2-openid-server/?branch=master)

This implements the OpenID specification on top of The PHP League's [OAuth2 Server](https://github.com/thephpleague/oauth2-server).
This library is based on the work of [OAuth 2.0 OpenID Server](https://github.com/dalpras/oauth2-openid-server).
We are all waiting for a OpenId Connect implementation in the [Official League OAuth2 Server](https://github.com/thephpleague/oauth2-server)!!

## Requirements

* Requires PHP version 7.0 or greater.
* [league/oauth2-server](https://github.com/thephpleague/oauth2-server) 5.1 or greater.

Note: league/oauth2-server version may have a higher PHP requirement.

## Usage
The following classes will need to be configured and passed to the AuthorizationServer in order to provide OpenID functionality.

1. IdentityRepository.  This MUST implement the DalPraS\OpenId\Server\Repositories\IdentityRepositoryInterface and return the identity of the user based on the return value of $accessToken->getUserIdentifier().
    1.1 The IdentityRepository MUST return a UserEntity that implements the following interfaces
      1.2 DalPraS\OpenId\Server\Entities\ClaimSetInterface
      1.3 League\OAuth2\Server\Entities\UserEntityInterface.
2. ClaimSet.  ClaimSet is a way to associate claims to a given scope.
3. ClaimExtractor. The ClaimExtractor takes an array of ClaimSets and in addition provides default claims for the OpenID specified scopes of: profile, email, phone and address.
1. IdTokenResponse. This class must be passed to the AuthorizationServer during construction and is responsible for adding the id_token to the response.
1. ScopeRepository. The getScopeEntityByIdentifier($identifier) method must return a ScopeEntity for the `openid` scope in order to enable support. See examples.

### Example Configuration

```php
// Init Repositories
$clientRepository       = new ClientRepository();
$scopeRepository        = new ScopeRepository();
$accessTokenRepository  = new AccessTokenRepository();
$authCodeRepository     = new AuthCodeRepository();
$refreshTokenRepository = new RefreshTokenRepository();

$privateKeyPath = 'file://' . __DIR__ . '/../private.key';
$publicKeyPath = 'file://' . __DIR__ . '/../public.key';

// OpenID Response Type
$responseType = new IdTokenResponse(new IdentityRepository(), new ClaimExtractor());

// Setup the authorization server
$server = new \League\OAuth2\Server\AuthorizationServer(
    $clientRepository,
    $accessTokenRepository,
    $scopeRepository,
    $privateKey,
    $publicKey,
    $responseType
);

$grant = new \DalPraS\OpenId\Server\Grant\AuthCodeGrant($authCodeRepository, $refreshTokenRepository,
            new \DateInterval(self::TTL_AUTH_CODE));


$grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month

// Enable the authentication code grant on the server
$server->enableGrantType(
    $grant,
    new \DateInterval('PT1H') // access tokens will expire after 1 hour
);

return $server;
```

After the server has been configured it should be used as described in the [OAuth2 Server documentation](https://oauth2.thephpleague.com/).

## OAuthServerExceptionPayloadDecorator

The Payload decorator change the `OAuthServerException` of the League package in an openid compatible version.
This is the example for an authorization code endpoint.

```php

    ...

    try {
        // Validate the HTTP request and return an AuthorizationRequest object.
        // The auth request object can be serialized into a user's session
        $authRequest = $server->validateAuthorizationRequest($request);

        // Once the user has logged in set the user on the AuthorizationRequest
        $authRequest->setUser($user);

        // Once the user has approved or denied the client update the status
        // (true = approved, false = denied)
        $authRequest->setAuthorizationApproved(true);

        // Return the HTTP redirect response
        return $server->completeAuthorizationRequest($authRequest, $response);

    } catch (OAuthServerException $e) {
        return (new OAuthServerExceptionPayloadDecorator($e))->generateHttpResponse($response);

    } catch (\Exception $e) {
        return (new OAuthServerExceptionPayloadDecorator((new OAuthServerException($e->getMessage(), 0, 'unknown_error', 500))))
            ->generateHttpResponse($response);
    }

```

For an access_token endpoint is possible to use the middlewares:


```php
        $middleware = new \DalPraS\OpenId\Server\Middleware\AuthorizationServerMiddleware($this->getAuthServer());
        return $middleware->__invoke($psrRequest, $psrResponse, function($request, $response) {
            return $response;
        });

```

## UserEntity
In order for this library to work properly you will need to add your IdentityProvider to the IdTokenResponse object.
This will be used internally to lookup a UserEntity by it's identifier.
Additionally your UserEntity must implement the ClaimSetInterface which includes a single method getClaims().
The getClaims() method should return a list of attributes as key/value pairs that can be returned if the proper scope has been defined.

```
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\UserEntityInterface;
use DalPraS\OpenId\Server\Entities\ClaimSetInterface;

class UserEntity implements UserEntityInterface, ClaimSetInterface
{
    use EntityTrait;

    protected $attributes;

    public function getClaims()
    {
        return $this->attributes;
    }
}

```

## ClaimSets

A ClaimSet is a scope that defines a list of claims.

```
// Example of the profile ClaimSet
$claimSet = new ClaimSetEntity('profile', [
        'name',
        'family_name',
        'given_name',
        'middle_name',
        'nickname',
        'preferred_username',
        'profile',
        'picture',
        'website',
        'gender',
        'birthdate',
        'zoneinfo',
        'locale',
        'updated_at'
    ]);
```

As you can see from the above, profile lists a set of claims that can be extracted from our UserEntity if the profile scope is included with the authorization request.

### Adding Custom ClaimSets
At some point you will likely want to include your own group of custom claims. To do this you will need to create a ClaimSetEntity, give it a scope (the value you will include in the scope parameter of your OAuth2 request) and the list of claims it supports.
```
$extractor = new ClaimExtractor();
// Create your custom scope
$claimSet = new ClaimSetEntity('company', [
        'company_name',
        'company_phone',
        'company_address'
    ]);
// Add it to the ClaimExtract (this is what you pass to IdTokenResponse, see configuration above)
$extractor->addClaimSet($claimSet);
```
Now, when you pass the company scope with your request it will attempt to locate those properties from your UserEntity::getClaims().

## Install

Via Composer

``` bash
$ composer require dalpras/oauth2-openid-server
```

## Testing

To run the unit tests you will need to require league/oauth2-server from the source as this repository utilizes some of their existing test infrastructure.

```bash
$ composer require league/oauth2-server --prefer-source
```

Run PHPUnit from the root directory:

```bash
$ vendor/bin/phpunit
```

## License

The MIT License (MIT). Please see [License File](https://github.com/dalpras/oauth2-openid-connect-client/blob/master/LICENSE) for more information.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
