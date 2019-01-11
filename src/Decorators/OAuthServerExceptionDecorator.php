<?php

namespace DalPraS\OpenId\Server\Decorators;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use League\OAuth2\Server\Exception\OAuthServerException;

class OAuthServerExceptionDecorator implements ResponseInterface {

    /**
     * @var OAuthServerException
     */
    private $response;

    public function __construct(OAuthServerException $response){
        $this->response = $response;
    }

    /**
     * Generate an OpendId HTTP response.
     * Change the payload to be compatible with OpenId.
     *
     * @param ResponseInterface $response
     * @param bool              $useFragment True if errors should be in the URI fragment instead of query string
     * @param int               $jsonOptions options passed to json_encode
     *
     * @return ResponseInterface
     */
    public function generateHttpResponse(ResponseInterface $response, $useFragment = false, $jsonOptions = 0) {
        $payload = $this->response->getPayload();
        if (!empty($payload) && isset($payload['message'])) {
            $payload['error_description'] = $payload['message'];
            unset($payload['message']);
            $this->response->setPayload($payload);
        }
        return $this->response->generateHttpResponse($response, $useFragment, $jsonOptions);
    }

    public function __call($method, $args){
        return $this->response->$method($args);
    }

    public function __set($key, $val){
        $this->response->$key = $val;
    }

    public function __get($key){
        return $this->response->$key;
    }
}
