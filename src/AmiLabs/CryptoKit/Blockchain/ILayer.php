<?php

namespace AmiLabs\CryptoKit\Blockchain;

interface ILayer
{
    const TXN_TYPE_SEND      = 0;
    const TXN_TYPE_ISSUANCE  = 20;
    const TXN_TYPE_DIVIDENDS = 50;

    /**
     * Returns some operational parameters for the server.
     *
     * @param  bool $ignoreLastBlockInfo  Flag specifying to ignore last block info if not available
     * @param  bool $logResult            Flag specifying to log result
     * @return array
     */
    public function getServerState($ignoreLastBlockInfo = FALSE, $logResult = FALSE);

    /**
     * Returns list of block transactions.
     *
     * @param  string $blockHash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlock($blockHash, $logResult = FALSE, $cacheResult = TRUE);

    /**
     * Returns detailed block information.
     *
     * @param  int  $blockIndex   Block index
     * @param  bool $logResult    Flag specifying to log result
     * @param  bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlockInfo($blockIndex, $logResult = FALSE, $cacheResult = TRUE);

    /**
     * Returns transaction raw hex with (or without) extended info.
     *
     * @param string $txHash     Transaction hash
     * @param bool $onlyHex      Return only tx raw hex if set to true
     * @param bool $logResult    Flag specifying to log result
     * @param bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getRawTransaction($txHash, $extended = FALSE, $logResult = FALSE, $cacheResult = TRUE);

    /**
     * Returns newest unconfirmed transactions.
     *
     * @param bool $logResult    Flag specifying to log result
     * @return array
     */
    public function getLastTransactions($logResult = FALSE);

    /**
     * Returns detailed block information.
     *
     * @param  string $txHash       Transaction hash
     * @param  bool   $hashPassed   Flag specifying that in previous argument passed hash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return array(
     *     'source'      => 'Source address',
     *     'destination' => 'Destination address',
     *     'asset'       => 'Asset',
     *     'quantity'    => 'Quantity',
     *     'type'        => ... // Tx type
     * )
     * @return mixed
     */
    public function getAssetInfoFromTx(
        $txHash,
        $hashPassed = TRUE,
        $logResult = FALSE,
        $cacheResult = TRUE
    );

    /**
     * Returns transactions from blocks filtered by passed asset.
     *
     * @param  array  $aAssets        List of assets
     * @param  array  $aBlockIndexes  List of block indexes
     * @param  bool   $logResult      Flag specifying to log result
     * @param  bool   $cacheResult    Flag specifying to cache result
     * @return array
     */
    public function getAssetTxsFromBlocks(
        array $aAssets,
        array $aBlockIndexes,
        $logResult = FALSE,
        $cacheResult = TRUE
    );

    /**
     * Returns wallets/assets balances.
     *
     * @param  array $aAssets       List of assets
     * @param  array $aWallets      List of wallets
     * @param  array $aExtraParams  Extra params
     * @param  bool  $logResult     Flag specifying to log result
     * @return array
     */
    public function getBalances(
        array $aAssets = array(),
        array $aWallets = array(),
        array $aExtraParams = array(),
        $logResult = FALSE
    );

    /**
     * Sends specified amount of asset from source to destination.
     *
     * @param string $source       Source address
     * @param string $destination  Destination address
     * @param string $asset        Asset name
     * @param int    $amount       Amount (in satoshi)
     * @param array  $aPublicKeys  List of public keys of all addresses
     * @param bool   $logResult    Flag specifying to log result
     * @return mixed
     */
    public function send($source, $destination, $asset, $amount, array $aPublicKeys = array(), $logResult = TRUE);

    /**
     * Signs raw tx.
     *
     * @param  string $rawData
     * @param  string $privateKey
     * @return string
     */
    public function signRawTx($rawData, $privateKey, $cacheResult = TRUE);


    /**
     * Returns wallets/assets balances from database.
     *
     * @param  array $aAssets   List of assets
     * @param  array $aWallets  List of wallets
     * @return array
     */
    // public function getBalancesFromDB(array $aAssets = array(),array $aWallets = array());
}
