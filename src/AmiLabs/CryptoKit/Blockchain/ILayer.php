<?php

namespace AmiLabs\CryptoKit\Blockchain;

interface ILayer{
    const TXN_TYPE_SEND     = 0;
    const TXN_TYPE_ISSUANCE = 20;

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
     * @param  int  $blockIndex   Block index
     * @param  bool $logResult    Flag specifying to log result
     * @param  bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getAssetInfoFromTxn($txnHash, $logResult = FALSE, $cacheResult = TRUE);

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
    );
}
