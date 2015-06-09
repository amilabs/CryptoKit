<?php

namespace AmiLabs\CryptoKit\Net\RPC\Client;

use InvalidArgumentException;

/**
 * Remote Procedure Call JSON client layer
 * supporting extra parameter _request_id.
 */
class JSONRequestId extends \Deepelopment\Net\RPC\Client\JSON{
    /**
     * Prepares request data using calling RPC method and parameters.
     *
     * @param  string $method
     * @param  array  $params
     * @return array
     * @throws InvalidArgumentException
     */
    protected function prepareRequest($method, array $params = NULL)
    {
        if(
            !is_array($params) ||
            !isset($params['_request_id']) ||
            '' === $params['_request_id']
        ){
            throw new InvalidArgumentException(
                "Missing obligatory '_request_id' parameter"
            );
        }
        $requestId = $params['_request_id'];
        unset($params['_request_id']);

        $request = parent::prepareRequest($method, $params);
        $request['_request_id'] = $requestId;

        return $request;
    }
}
