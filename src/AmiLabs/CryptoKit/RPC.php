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
    * Response cache rules
    *
    * @var array
    */
    private static $aResponseCacheRules = array();
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
        $testnet =
            FALSE === Registry::useStorage('CFG')->get('CryptoKit/testnet', FALSE)
                ? ''
                : '-testnet';
        $servicesCfgKey = 'CryptoKit/RPC/services';
        // Use services-testnet key if testnet flag is set to true and key exists
        if($testnet && Registry::useStorage('CFG')->exists($servicesCfgKey . $testnet)){
            $servicesCfgKey .= $testnet;
        }
        $aConfigs = Registry::useStorage('CFG')->get($servicesCfgKey, FALSE);
        if(is_array($aConfigs)){
            $needToSearchConfig = true;
            $oCache = Cache::get('rpc-service' . $testnet);
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
        }
        if(!is_array($aConfig)){
            throw new \Exception('Blockchain RPC configuration missing');
        }
        self::$aConfig = $aConfig;
    }
    /**
     * Execute JSON RPC command.
     *
     * @param  string $command   RPC call command
     * @param  mixed  $aParams   RPC call parameters
     * @param  bool   $log       Request and result data will be logged if true
     * @param  bool   $cache     Result data will be cached if true (not recommended for send/broadcast)
     * @return array
     * @throws Exception
     */
    public function exec($daemon, $command, $aParams = array(), $log = FALSE, $cache = FALSE, $skipExceptionTracking = FALSE){

        // Check if daemon is known
        if(!isset($this->aServices[$daemon]) || !is_object($this->aServices[$daemon])){
            throw new \Exception(
                isset($this->aServices[$daemon])
                    ? sprintf(
                        "Invalid daemon '%s' object:\n%s",
                        $daemon,
                        var_export($this->aServices[$daemon])
                    ) : sprintf(
                        "Unknown daemon '%s'",
                        $daemon
                    )
            );
        }

        /* @var $oLogger \AmiLabs\Logger */
        $oLogger = Logger::get('rpc-' . $daemon, FALSE, TRUE);
        if($log){
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
                if($log){
                    $oLogger->log('CACHE: Data loaded from cache');
                }
            }
        }
        if(!isset($aResult)){
            try {
                $aResult = $this->aServices[$daemon]->exec($command, $aParams, $oLogger);
                if($cache){
                    if(!is_null($aResult) && $this->canCacheResponse($daemon, $command, $aResult)){
                        $oCache->save($aResult);
                        if($log){
                            $oLogger->log('CACHE: Response was stored in cache');
                        }
                    }else{
                        if($log){
                            $oLogger->log('CACHE: Response was NOT stored in cache due to cache rules or NULL value');
                        }
                    }
                }
            }catch(\Exception $e){
                if($log){
                    $oLogger->log('ERROR: ' . var_export($e->getCode(), true) . ' ' . var_export($e->getMessage(), true));
                }
                $srvCode = substr($daemon, 0, 1) . substr($daemon, -2);
                $cmdCode = '';
                $aCommandParts = explode('_', $command);
                if(sizeof($aCommandParts) < 2){
                    $cmdCode = substr($aCommandParts[0], 0, 4);
                }else{
                    foreach($aCommandParts as $commandPart){
                        $cmdCode .= substr($commandPart, 0, 1);
                    }
                }

                $node = '';
                if(FALSE !== strpos($e->getMessage(), 'node1.')){
                    $node = 1;
                }elseif(FALSE !== strpos($e->getMessage(), 'node2.')){
                    $node = 2;
                }

                if(!$skipExceptionTracking && !isset($GLOBALS['JSONRPC/State'])){
                    $GLOBALS['JSONRPC/State'] = json_encode(
                        array(
                            'srvCode' => $srvCode,
                            'cmdCode' => $cmdCode,
                            'expCode' => $e->getCode(),
                            'expMess' => $e->getMessage(),
                            'expTrce' => $e->getTraceAsString(),
                            'node'    => $node
                        )
                    );
                }
                $oException = new \Exception($e->getMessage(), $e->getCode(), $e);
                $oException->srvCode = $srvCode;
                $oException->cmdCode = $cmdCode;
                throw $oException;
            }
        }
        if($log){
            $oLogger->log('Result: ' . var_export($aResult, true));
        }
        return $aResult;
    }
    /**
     * Adds cache rule callback for specified method.
     *
     * @param string $daemon
     * @param string $method
     * @param callable $callback
     * @see AmiLabs\CryptoKit\RPC::canCacheResponse
     */
    public function addCacheRule($daemon, $method, $callback){
        if(!isset(self::$aResponseCacheRules[$daemon])){
            self::$aResponseCacheRules[$daemon] = array();
        }
        if(!isset(self::$aResponseCacheRules[$daemon][$method])){
            self::$aResponseCacheRules[$daemon][$method] = array();
        }
        self::$aResponseCacheRules[$daemon][$method][] = $callback;
    }
    /**
     * Checks if a response is valid for specified method.
     *
     * @param string $daemon
     * @param string $method
     * @param mixed $response  Response to check
     * @return bool
     * @see AmiLabs\CryptoKit\RPC::addCacheRule
     */
    protected function canCacheResponse($daemon, $method, $response){
        $result = TRUE;
        if(isset(self::$aResponseCacheRules[$daemon]) && isset(self::$aResponseCacheRules[$daemon][$method])){
            foreach(self::$aResponseCacheRules[$daemon][$method] as $callback){
                $result = $result && call_user_func($callback, $response);
            }
        }
        return $result;
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
