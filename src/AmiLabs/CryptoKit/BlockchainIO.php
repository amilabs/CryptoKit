<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\CryptoKit\RPC;
use \AmiLabs\DevKit\Cache;
use \AmiLabs\DevKit\Logger;
use \AmiLabs\CryptoKit\Blockchain;
use \AmiLabs\CryptoKit\Blockchain\Layer;

/**
 * Blockchain I/O Facade.
 */
class BlockchainIO{
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
     * Layer name
     *
     * @var string
     */
    protected $layerName = 'Counterparty';

    /**
     * Layer driver.
     *
     * @var \AmiLabs\CryptoKit\Blockchain\ILayer
     */
    protected $oLayer;

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
     * @param  int  $blockIndex   Block index
     * @param  bool $logResult    Flag specifying to log result
     * @param  bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlockInfo($blockIndex, $logResult = FALSE, $cacheResult = TRUE){
        return
            $this->oLayer->execCounterpartyd(
                'get_block_info',
                array('block_index' => $blockIndex),
                $logResult,
                $cacheResult
            );
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
        return
            $this->oRPC->execBitcoind(
                'getblock',
                array($blockHash),
                $logResult,
                $cacheResult
            );
    }

    /**
     * Contructor.
     */
    protected function __construct(){
        $this->oRPC = new RPC();
        $this->oLayer = new $this->layerName;
    }
}
