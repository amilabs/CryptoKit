<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\CryptoKit\RPC;
use \AmiLabs\DevKit\Cache;
use \AmiLabs\DevKit\Logger;

/**
 * Blockchain I/O Facade.
 */
class BlockchainIO {
    /**
     * Singleton instance
     *
     * @var \AmiLabs\CryptoKit\BlockchainIO
     */
    protected static $oInstance;
    /**
     * RPC execution object
     *
     * @var \AmiLabs\CryptoKit\RPC
     */
    protected $oRPC;
    /**
     * Singleton implementation.
     *
     * @return \AmiLabs\CryptoKit\BlockchainIO
     */
    public static function getInstance(){
        if(is_null(self::$oInstance)){
            self::$oInstance = new BlockchainIO();
        }
        return self::$oInstance;
    }
    /**
     * Returns detailed block information.
     *
     * @param type $block
     * @param type $logResult
     * @param type $cacheResult
     * @return type
     */
    public function getBlockInfo($block, $logResult = false, $cacheResult = true){
        return $this->oRPC->execCounterpartyd('get_block_info', array('block_index' => $block), $logResult, $cacheResult);
    }
    /**
     * Returns list of block transactions.
     *
     * @param type $block
     * @param type $logResult
     * @param type $cacheResult
     * @return type
     */
    public function getBlock($block, $logResult = false, $cacheResult = true){
        return $this->oRPC->execBitcoind('getblock', array($blockHash), $logResult, $cacheResult);
    }
    /**
     * Contructor.
     */
    protected function __construct(){
        $this->oRPC = new RPC();
    }
}