<?php

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Zend\Diactoros\Stream;
use DalPraS\OpenId\Server\ResponseTypes\IdTokenJwtResponse;
use DalPraS\OpenId\ServerExamples\Repositories\IdentityRepository;
use DalPraS\OpenId\ServerExamples\Repositories\ScopeRepository;
use DalPraS\OpenId\Server\ClaimExtractor;

include __DIR__ . '/../vendor/autoload.php';

$app = new App([
    'settings'                 => [
        'displayErrorDetails' => true,
    ],
    AuthorizationServer::class => function () {
        // Init our repositories
        $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
        $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
        $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

        // Path to public and private keys
        $privateKey = 'file://' . __DIR__ . '/../private.key';
        //$privateKey = new CryptKey('file://path/to/private.key', 'passphrase'); // if private key has a pass phrase

        // OpenID Response Type
        $responseType = new IdTokenJwtResponse(new IdentityRepository(), new ClaimExtractor());

        // Setup the authorization server
        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen',
            $responseType
        );

        // Enable the client credentials grant on the server
        $server->enableGrantType(
            new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
            new \DateInterval('PT1H') // access tokens will expire after 1 hour
        );

        return $server;
    },
]);

$app->post('/access_token', function (ServerRequestInterface $request, ResponseInterface $response) use ($app) {

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

});

$app->run();
