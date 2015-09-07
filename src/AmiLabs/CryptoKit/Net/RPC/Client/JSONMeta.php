<?php

namespace AmiLabs\CryptoKit\Net\RPC\Client;

/**
 * Remote Procedure Call JSON client layer supporting metadata.
 */
class JSONMeta extends \Deepelopment\Net\RPC\Client\JSONMeta
{
    /**
     * Returns metadata parameters.
     *
     * @return array
     */
    protected function getMetaParams(){
        return array(
            /*
            '_request_id',
            '_request_count',
             */
        );
    }
}
