<?php

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\RefreshTokenRepository;
use OAuth2ServerExamples\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use DalPraS\OpenId\Server\IdTokenResponse;
use DalPraS\OpenId\ServerExamples\Repositories\IdentityRepository;
use DalPraS\OpenId\ServerExamples\Repositories\ScopeRepository;
use DalPraS\OpenId\Server\ClaimExtractor;

include __DIR__ . '/../vendor/autoload.php';

$app = new App([
    // Add the authorization server to the DI container
    AuthorizationServer::class => function () {
        // OpenID Response Type
        $responseType = new IdTokenResponse(new IdentityRepository(), new ClaimExtractor());

        // Setup the authorization server
        $server = new AuthorizationServer(
            new ClientRepository(),                 // instance of ClientRepositoryInterface
            new AccessTokenRepository(),            // instance of AccessTokenRepositoryInterface
            new ScopeRepository(),                  // instance of ScopeRepositoryInterface
            'file://' . __DIR__ . '/../private.key',    // path to private key
            'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen',      // encryption key
            $responseType
        );

        $grant = new PasswordGrant(
            new UserRepository(),           // instance of UserRepositoryInterface
            new RefreshTokenRepository()    // instance of RefreshTokenRepositoryInterface
        );
        $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month

        // Enable the password grant on the server with a token TTL of 1 hour
        $server->enableGrantType(
            $grant,
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );

        return $server;
    },
]);

$app->post(
    '/access_token',
    function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {

        /* @var \League\OAuth2\Server\AuthorizationServer $server */
        $server = $app->getContainer()->get(AuthorizationServer::class);
        try {
            return $server->respondToAccessTokenRequest($request, $response);

        } catch (OAuthServerException $exception) {
            $payloadException = new OAuthServerExceptionPayloadDecorator($exception);
            return $payloadException->generateHttpResponse($response);

        } catch (Exception $exception) {
            $oauthException = new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500);
            $payloadException = new OAuthServerExceptionPayloadDecorator($oauthException);
            return $payloadException->generateHttpResponse($response);
        }
    }
);

$app->run();
