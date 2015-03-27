<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\CryptoKit\RPC;
use \AmiLabs\DevKit\Cache;
use \AmiLabs\DevKit\Logger;
use \AmiLabs\CryptoKit\Blockchain;

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
     * Returns detailed block information.
     *
     * @param  int  $blockIndex   Block index
     * @param  bool $logResult    Flag specifying to log result
     * @param  bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlockInfo($blockIndex, $logResult = FALSE, $cacheResult = TRUE){
        return
            $this->oLayer->getBlockInfo(
                $blockIndex,
                $logResult,
                $cacheResult
            );
    }

    /**
     * Returns detailed block information.
     *
     * @param  int  $blockIndex   Block index
     * @param  bool $logResult    Flag specifying to log result
     * @param  bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getAssetInfoFromTxn($txnHash, $logResult = FALSE, $cacheResult = TRUE){
        return
            $this->oLayer->getAssetInfoFromTxn($txnHash, $logResult, $cacheResult);
    }

    /**
     * Returns transactions from blocks filtered by passed assets.
     *
     * @param  array $aAssets        Assets
     * @param  array $aBlockIndexes  Block indexes
     * @param  bool $logResult       Flag specifying to log result
     * @param  bool $cacheResult     Flag specifying to cache result
     * @return mixed
     */
    public function getAssetsTxnsFromBlocks(
        array $aAssets,
        array $aBlockIndexes,
        $logResult = FALSE,
        $cacheResult = TRUE
    ){
        return
            $this->oLayer->getAssetsTxnsFromBlocks(
                $aAssets,
                $aBlockIndexes,
                $logResult,
                $cacheResult
            );
    }

    /**
     * Contructor.
     */
    protected function __construct(){
        $this->oRPC = new RPC();
        $class = "\\AmiLabs\\CryptoKit\\Blockchain\\Layer\\" . $this->layerName;
        $this->oLayer = new $class;
    }
}
