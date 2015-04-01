<?php

namespace AmiLabs\CryptoKit\Blockchain;

interface ILayer
{
    const TXN_TYPE_SEND     = 0;
    const TXN_TYPE_ISSUANCE = 20;

    /**
     * Returns some operational parameters for the server.
     *
     * @param  bool $logResult  Flag specifying to log result
     * @return array
     */
    public function getServerState($logResult = FALSE);

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
     * Returns detailed block information.
     *
     * @param  string $txHash       Transaction hash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return array('type' => ..., 'asset' => ..., 'quantity' => ..., 'type' => ...)
     * @return mixed
     */
    public function getAssetInfoFromTx($txHash, $logResult = FALSE, $cacheResult = TRUE);

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
    );

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
    );
}
