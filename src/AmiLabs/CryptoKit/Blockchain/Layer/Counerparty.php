<?php

namespace AmiLabs\CryptoKit\Blockchain\Layer;

use \AmiLabs\CryptoKit\Blockchain;
use \AmiLabs\CryptoKit\RPC;

class Counterparty implements ILayer{
    /**
     * RPC execution object
     *
     * @var \AmiLabs\CryptoKit\RPC
     */
    protected $oRPC;

    public function __construct(){
        $this->oRPC = new RPC;
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
            $this->oRPC->execCounterpartyd(
                'get_block_info',
                array('block_index' => $blockIndex),
                $logResult,
                $cacheResult
            );
    }
}
