<?php

namespace DalPraS\OpenId\Server\Decorators;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The OAuthServerException thrown by League OauthServer is together a PSR ResponseMessage and
 * an Exception.
 * As a PSR ResponseMessage it works as a Response. 
 * In league/oauth-server version 7.4.0 the error_description is implemented for compatibility with OpenId.
 * 'hint' and 'message' are removed.  
 * By using this decorator che message object is wrapped changing to a correct one.
 */
class OAuthServerExceptionPayloadDecorator {

    /**
     * @var OAuthServerException
     */
    private $response;

    public function __construct(OAuthServerException $response){
        $this->response = $response;
        $payload = $this->response->getPayload();
        if ( !empty($payload) ) {

            // $messages = [];
            if (isset($payload['hint'])) {
                // $messages[] = $payload['hint'];
                unset($payload['hint']);
            }

            if (isset($payload['message'])) {
                // $messages[] = $payload['message'];
                unset($payload['message']);
            }

            // $payload['error_description'] = implode('. ', $messages);
            $this->response->setPayload($payload);
        }
    }

    public function __call($method, $args) {
        if (is_callable([$this->response, $method])) {
            return call_user_func_array([$this->response, $method], $args);
        }
        throw new \Exception('Undefined method - ' . get_class($this->response) . '::' . $method);
    }

    public function __get($property) {
        if (property_exists($this->response, $property)) {
            return $this->response->$property;
        }
    }

    public function __set($property, $value) {
        $this->response->$property = $value;
        return $this;
    }
}
