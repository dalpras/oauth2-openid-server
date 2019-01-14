<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace DalPraS\OpenId\Server\Middleware;

use Exception;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use DalPraS\OpenId\Server\Decorators\OAuthServerExceptionPayloadDecorator;

class AuthorizationServerMiddleware
{
    /**
     * @var AuthorizationServer
     */
    private $server;

    /**
     * @param AuthorizationServer $server
     */
    public function __construct(AuthorizationServer $server)
    {
        $this->server = $server;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        try {
            $response = $this->server->respondToAccessTokenRequest($request, $response);

        } catch (OAuthServerException $exception) {
            $payloadException = new OAuthServerExceptionPayloadDecorator($exception);
            return $payloadException->generateHttpResponse($response);

        } catch (Exception $exception) {
            $oauthException = new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500);
            $payloadException = new OAuthServerExceptionPayloadDecorator($oauthException);
            return $payloadException->generateHttpResponse($response);

        }

        // Pass the request and response on to the next responder in the chain
        return $next($request, $response);
    }
}
