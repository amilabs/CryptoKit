<?php

namespace AmiLabs\CryptoKit\Net\RPC\Server;

use InvalidArgumentException;

/**
 * Remote Procedure Call JSON server layer
 * supporting extra parameter _request_id.
 */
class JSONRequestId extends Deepelopment\Net\RPC\Server\JSON{
    /**
     * @var string
     */
    protected $requestId;

    /**
     * Returns request Id.
     *
     * @return string
     */
    public function getRequestId(){
        return $this->requestId;
    }

    /**
     * Validates request.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateRequest(){
        parent::validateRequest();

        if(
            !isset($this->request['_request_id']) ||
            '' === $this->request['_request_id']
        ){
            throw new InvalidArgumentException(
                "Missing obligatory '_request_id' parameter"
            );
        }
        $this->requestId = (string)$this->request['_request_id'];
    }
}
