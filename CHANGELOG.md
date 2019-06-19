# Changelog
All Notable changes to `oauth2-openid-server` will be documented in this file

## v2.0 - 2019-06-19

Now there are `token opaque` and `token jwt` for different kind of services. 
Opaque tokens are for services that have small access_token size (amazon, tuya, ecc.).  
All token now are moved in ResponseTypes folder.  

### Added

Splitted the IdTokenResponse in two different ResponseType. In this way, now is possible to return a Jwt or an Opaque access_token format.

- IdTokenJwtResponse
- IdTokenOpaqueResponse
- useJwt in ClientEntity define which token to use 

### Removed

With the latest change in league/oauth-server for returning `error_description` these class doesnt need to exist anymore:

- src/Decorators/OAuthServerExceptionPayloadDecorator.php
- src/Middleware/AuthorizationServerMiddleware.php
- src/Middleware/ResourceServerMiddleware.php

## v1.0 - 2019-01-14

### Added

- Initial release

### Deprecated

- Nothing

### Fixed

- Nothing

### Removed

- Nothing

### Security

- Nothing



