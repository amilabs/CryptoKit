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
     * @var \AmiLabs\JSONRPC\RPC\Client\JSON
     */
    protected $oClient;

    /**
     * Constructor.
     *
     * @param array $aConfig  Driver configuration
     */
    public function __construct(array $aConfig){
        $this->oClient = \AmiLabs\JSONRPC\RPC::getLayer(
            // 'AmiLabs\\CryptoKit\\Net\\RPC\\Client\\JSON',
            'JSON',
            \AmiLabs\JSONRPC\RPC::TYPE_CLIENT,
            array(
                CURLOPT_SSL_VERIFYPEER => FALSE, // Todo: use from configuration, only for HTTPS
                CURLOPT_SSL_VERIFYHOST => FALSE,
                'AmiLabs\\Logger' =>
                    Registry::useStorage('CFG')->get('AmiLabs\\Logger', array())
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
