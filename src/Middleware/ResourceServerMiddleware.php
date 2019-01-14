<?php

namespace DalPraS\OpenId\Server\Middleware;

use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use DalPraS\OpenId\Server\Decorators\OAuthServerExceptionPayloadDecorator;

/**
 * Replace the League ResourceServerMiddleware changing the payload
 * for a valid OpenId response.
 *
 * @see \League\OAuth2\Server\Middleware\ResourceServerMiddleware
 */
class ResourceServerMiddleware
{
    /**
     * @var ResourceServer
     */
    private $server;

    /**
     * @param ResourceServer $server
     */
    public function __construct(ResourceServer $server)
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
            $request = $this->server->validateAuthenticatedRequest($request);
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
