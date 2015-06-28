<?php

namespace AmiLabs\CryptoKit\Net\RPC\Server;

use AmiLabs\DevKit\Logging;

/**
 * Remote Procedure Call JSON server layer supporting metadata.
 */
class JSONMeta extends \Deepelopment\Net\RPC\Server\JSONMeta{
    /**
     * Returns metadata parameters.
     *
     * @return array
     */
    protected function getMetaParams(){
        return array(
            '_request_id',
            '_request_count',
        );
    }
}
