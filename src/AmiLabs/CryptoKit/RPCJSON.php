<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\DevKit\Registry;
use \AmiLabs\CryptoKit\IRPCServiceClient;
use \AmiLabs\CryptoKit\RPCServiceClient;

/**
 * Class for JSON RPC execution.
 */
class RPCJSON extends RPCServiceClient implements IRPCServiceClient{
    /**
     * JSON RPC Client object
     *
     * @var \AmiLabs\JSON-RPC\RPC\Client\JSON
     */
    protected $oClient;

    /**
     * Constructor.
     *
     * @param array $aConfig  Driver configuration
     */
    public function __construct(array $aConfig){
        $this->oClient = \AmiLabs\JSON-RPC\RPC::getLayer(
            // 'AmiLabs\\CryptoKit\\Net\\RPC\\Client\\JSON',
            'JSON',
            \AmiLabs\JSON-RPC\RPC::TYPE_CLIENT,
            array(
                CURLOPT_SSL_VERIFYPEER => FALSE, // Todo: use from configuration, only for HTTPS
                CURLOPT_SSL_VERIFYHOST => FALSE,
                'Deepelopment\\Logger' =>
                    Registry::useStorage('CFG')->get('Deepelopment\\Logger', array())
            )
        );
        $this->oClient->open($aConfig['address']);
    }

    /**
     * Execute JSON RPC command.
     *
     * @param string $command
     * @param array $aParams
     * @return array
     */
    public function exec($command, array $aParams){
        return $this->oClient->execute(
            $command,
            $aParams,
            array(
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 30,
            )
        );
    }
}
