<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\CryptoKit\RPCServiceClient;

/**
 * Class for JSON RPC execution.
 */
class RPCJSON extends RPCServiceClient{
    /**
     * JSON RPC Client object
     *
     * @var \JsonRPC\Client
     */
    protected $oClient;
    /**
     * Constructor.
     *
     * @param array $aConfig  Driver configuration
     */
    public function __construct(array $aConfig){
        $oRPCClient = new \Deepelopment\Net\RPC(
            'JSON',
            RPC::TYPE_CLIENT,
            array(
                CURLOPT_SSL_VERIFYPEER => FALSE, // Todo: use from configuration, only for HTTPS
                CURLOPT_SSL_VERIFYHOST => FALSE
            )
        );
        $this->oClient = $oRPCClient->getLayer();
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
        return $this->oClient->execute($command, $aParams);
    }
}
