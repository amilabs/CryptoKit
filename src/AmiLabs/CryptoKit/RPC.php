<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\DevKit\Logger;

/**
 * Class for JSON RPC execution.
 */
class RPC {
    /**
     * List of available services
     *
     * @var array 
     */
    private $aServices;
    /**
     * Services configuration
     *
     * @var array
     */
    private $aConfig;
    /**
     * Constructor.
     */
    public function __construct(){
        $this->aConfig = Registry::useStorage('CFG')->get('RPCServices');
        foreach($this->aConfig as $daemon => $aDaemonConfig){
            if(strpos($aDaemonConfig['driver'], '\\') !== FALSE){
                $className = $aDaemonConfig['driver'];
            }else{
                $className = '\\AmiLabs\\CryptoKit\\RPC' . strtoupper($aDaemonConfig['driver']);
            }
            if(class_exists($className)){
                $this->aServices[$daemon] = new $className($aDaemonConfig);
            }else{
                throw new \Exception('RPC driver class ' . $className . ' not found');
            }
        }
    }
    /**
     * Execute JSON RPC command.
     *
     * @param string $command   RPC call command
     * @param mixed $aParams    RPC call parameters
     * @param bool $log         Request and result data will be logged if true
     * @param bool $cache       Result data will be cached if true (not recommended for send/broadcast)
     * @return array
     */
    public function exec($daemon, $command, $aParams = array(), $log = false, $cache = false){

        // Check if daemon is known
        if(!in_array($daemon, array_keys($this->aServices))){
            throw new \Exception("Unknown daemon: " . $daemon, -1);
        }
        $oLogger = null;
        if($log){
            /* @var $oLogger \AmiLabs\Logger */
            $oLogger = Logger::get('rpc-' . $daemon);
            $oLogger->log(Logger::DELIMITER);
            $oLogger->log('Call to: ' . $daemon . ' (' . $this->aConfig[$daemon]['address'] .')');
            $oLogger->log('Execute command: ' . $command);
            $oLogger->log('Params: ' . var_export($aParams, true));

        }
        $cacheName = $daemon . '_' . $command . '_' . md5(serialize($aParams));

        /* @var $oCache \AmiLabs\DevKit\FileCache */
        $oCache = Cache::get($cacheName);
        if($cache && $oCache->exists()){
            $aResult = unserialize($oCache->load());
        }else{
            try {
                $aResult = $this->aServices[$daemon]->exec($command, $aParams, $oLogger);
                $oCache->save(serialize($aResult));
            }catch(\Exception $e){
                if($log){
                    $oLogger->log('ERROR: ' . var_export($e->getMessage(), true));
                }
                throw new \Exception($e->getMessage(), -1, $e);
            }
        }
        if($log){
            $oLogger->log('Result: ' . var_export($aResult, true));
        }
        return $aResult;
    }
    /**
     * Execute counterpartyd method via counterblockd proxy.
     *
     * @param string $command
     * @param array $aParams
     * @param bool $logRequest
     * @return array
     */
    public function execCounterpartyd($command, array $aParams = array(), $logRequest = false, $cacheResponse = false){
        return $this->execCounterblockd(
            'proxy_to_counterpartyd',
            array(
                'method' => $command, 
                'params' => $aParams
            ),
            $logRequest,
            $cacheResponse
        );
    }
    /**
     * Execute counterblockd JSON RPC command.
     *
     * @param string $command
     * @param array $aParams
     * @param bool $logRequest
     * @return array
     */
    public function execCounterblockd($command, array $aParams = array(), $logRequest = false, $cacheResponse = false){
        return $this->exec('counterblockd', $command, $aParams, $logRequest, $cacheResponse);
    }

    /**
     * Execute bitcoind JSON RPC command.
     *
     * @param string $command
     * @param moxed $aParams
     * @param bool $logRequest
     * @return array
     */
    public function execBitcoind($command, $aParams = array(), $logRequest = false, $cacheResponse = false){
        return $this->exec('bitcoind', $command, $aParams, $logRequest, $cacheResponse);
    }
}
/**
 * Interface for RPC Service client classes.
 */
interface IRPCServiceClient {
    /**
     * Executes RPC call.
     *
     * @param string $command           Command to execute
     * @param array $aParams            Parameters
     */
    public function exec($command, array $aParams);
}
/**
 * RPC Service client abstract class.
 */
abstract class RPCServiceClient implements IRPCServiceClient{
}
