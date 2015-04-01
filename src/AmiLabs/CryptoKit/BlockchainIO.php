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
     * Returns some operational parameters for the server.
     *
     * @param  bool $logResult  Flag specifying to log result
     * @return array
     */
    public function getServerState($logResult = FALSE)
    {
        return
            $this->oLayer->getServerState($logResult);
    }

    /**
     * Returns list of block transactions.
     *
     * @param  string $blockHash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlock($blockHash, $logResult = false, $cacheResult = true){
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
     * @param  string $txHash       Transaction hash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return array('type' => ..., 'asset' => ..., 'quantity' => ..., 'type' => ...)
     * @return mixed
     */
    public function getAssetInfoFromTx($txHash, $logResult = FALSE, $cacheResult = TRUE){
        return
            $this->oLayer->getAssetInfoFromTx($txHash, $logResult, $cacheResult);
    }

    /**
     * Returns transactions from blocks filtered by passed asset.
     *
     * @param  string $asset          Asset
     * @param  array  $aBlockIndexes  List of block indexes
     * @param  bool   $logResult      Flag specifying to log result
     * @param  bool   $cacheResult    Flag specifying to cache result
     * @return array
     */
    public function getAssetTxsFromBlocks(
        $asset,
        array $aBlockIndexes,
        $logResult = FALSE,
        $cacheResult = TRUE
    ){
        return
            $this->oLayer->getAssetTxsFromBlocks(
                $asset,
                $aBlockIndexes,
                $logResult,
                $cacheResult
            );
    }

    /**
     * Returns wallets/assets balances.
     *
     * @param  array $aAssets    List of assets
     * @param  array $aWallets   List of wallets
     * @param  bool  $logResult  Flag specifying to log result
     * @return array
     */
    public function getBalances(
        array $aAssets = array(),
        array $aWallets = array(),
        $logResult = FALSE
    ){
        return
            $this->oLayer->getBalances(
                $aAssets,
                $aWallets,
                $logResult
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
