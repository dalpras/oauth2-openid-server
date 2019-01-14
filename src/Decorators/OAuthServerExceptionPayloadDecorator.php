<?php

namespace DalPraS\OpenId\Server\Decorators;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The OAuthServerException thrown by League OauthServer is together a PSR ResponseMessage and
 * an Exception.
 * As a PSR ResponseMessage it works as a Response. The Error Message generated is not a valid OpenId
 * error message because it lacks of the 'error_description'.
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
            if (isset($payload['hint'])) {
                $payload['error_description'] = $payload['hint'];
                unset($payload['hint']);
            }

            if (isset($payload['message'])) {
                $payload['error_description'] = $payload['message'];
                unset($payload['message']);
            }

            $this->response->setPayload($payload);
        }
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
//     public function generateHttpResponse(ResponseInterface $response, $useFragment = false, $jsonOptions = 0) {
//         $payload = $this->response->getPayload();
//         if ( !empty($payload) ) {
//             if (isset($payload['hint'])) {
//                 $payload['error_description'] = $payload['hint'];
//                 unset($payload['hint']);
//             }

//             if (isset($payload['message'])) {
//                 $payload['error_description'] = $payload['message'];
//                 unset($payload['message']);
//             }

//             $this->response->setPayload($payload);
//         }
//         return $this->response->generateHttpResponse($response, $useFragment, $jsonOptions);
//     }

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
