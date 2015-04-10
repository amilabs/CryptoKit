<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\DevKit\Registry;
use \AmiLabs\DevKit\Cache;
use \AmiLabs\DevKit\Logger;
use \AmiLabs\CryptoKit\BlockchainIO;

/**
 * Class for JSON RPC execution.
 */
class RPC {
    /**
     * Default protocol for RPC services
     *
     * @todo: use HTTPS
     */
    const DEFAULT_PROTOCOL = 'https';
    /**
     * Configuration check interval
     */
    const CHECK_INTERVAL = 600; // 20 minutes
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
    private static $aConfig;
    /**
     * Constructor.
     */
    public function __construct($checkServices = true){
        if(is_null(self::$aConfig)){
            $this->loadConfiguration($checkServices);
        }
        foreach(self::$aConfig as $daemon => $aDaemonConfig){
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
     * Reads RPC services configuration from global config.
     *
     * @param mixed $checkServices
     */
    protected function loadConfiguration($checkServices){
        $aConfigs = Registry::useStorage('CFG')->get('CryptoKit/RPC/services', FALSE);

        if(is_array($aConfigs)){
            $needToSearchConfig = true;
            $oCache = Cache::get('rpc-service');
            if($oCache->exists() && !$oCache->clearIfOlderThan(self::CHECK_INTERVAL)){
                $aConfig = $oCache->load();
                if($checkServices){
                    // Check if service is working
                    if(BlockchainIO::getInstance()->checkServerConfig($aConfig)){
                        $needToSearchConfig = false;
                    }else{
                        $oCache->clear();
                    }
                }
            }
            if($needToSearchConfig){
                // Get working config
                foreach($aConfigs as $aConfig){
                    foreach($aConfig as $daemon => $aDaemonConfig){
                        $address = isset($aDaemonConfig['address']) ? $aDaemonConfig['address'] : FALSE;
                        if(strpos($address, 'http') !== 0){
                            $address = self::DEFAULT_PROTOCOL . '://' . $address;
                        }
                        $aDaemonConfig['address'] = $address;
                        if(!isset($aDaemonConfig['driver'])){
                            $aDaemonConfig['driver'] = 'json';
                        }
                        $aConfig[$daemon] = $aDaemonConfig;
                    }
                    if($checkServices){
                        // Check if service is working
                        if(BlockchainIO::getInstance()->checkServerConfig($aConfig)){
                            $oCache->save($aConfig);
                            break;
                        }
                    }else{
                        break;
                    }
                }
            }
        }else{
            // Old Style Config (todo: deprecate)
            $aConfig = Registry::useStorage('CFG')->get('RPCServices', FALSE);
        }
        if(!is_array($aConfig)){
            throw new \Exception('Blockchain RPC configuration missing');
        }
        self::$aConfig = $aConfig;
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
            $oLogger->log('Call to: ' . $daemon . ' (' . self::$aConfig[$daemon]['address'] .')');
            $oLogger->log('Execute command: ' . $command);
            $oLogger->log('Params: ' . var_export($aParams, true));

        }
        $cacheName = $daemon . '_' . $command . '_' . md5(serialize($aParams));

        /* @var $oCache \AmiLabs\DevKit\FileCache */
        if($cache){
            $oCache = Cache::get($cacheName);
            if($oCache->exists()){
                $aResult = $oCache->load();
            }
        }
        if(!isset($aResult)){
            try {
                $aResult = $this->aServices[$daemon]->exec($command, $aParams, $oLogger);
                if($cache){
                    $oCache->save($aResult);
                }
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
     * @deprecated
     */
    public function execCounterpartyd($command, array $aParams = array(), $logRequest = false, $cacheResponse = false){
        return $this->exec('counterpartyd', $command, $aParams, $logRequest, $cacheResponse);
        /*
        return $this->execCounterblockd(
            'proxy_to_counterpartyd',
            array(
                'method' => $command,
                'params' => $aParams
            ),
            $logRequest,
            $cacheResponse
        );
        */
    }
    /**
     * Execute counterblockd JSON RPC command.
     *
     * @param string $command
     * @param array $aParams
     * @param bool $logRequest
     * @return array
     * @deprecated
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
     * @deprecated
     */
    public function execBitcoind($command, $aParams = array(), $logRequest = false, $cacheResponse = false){
        return $this->exec('bitcoind', $command, $aParams, $logRequest, $cacheResponse);
    }
}
