<?php

namespace AmiLabs\CryptoKit\Blockchain\Layer;

use AmiLabs\CryptoKit\Blockchain\ILayer;
use AmiLabs\CryptoKit\RPC;
use Moontoast\Math\BigNumber;

class Counterparty implements ILayer
{
    /**
     * RPC execution object
     *
     * @var \AmiLabs\CryptoKit\RPC
     */
    protected $oRPC;

    public function __construct()
    {
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
    public function getBlockInfo($blockIndex, $logResult = FALSE, $cacheResult = TRUE)
    {
        return
            $this->oRPC->execCounterpartyd(
                'get_block_info',
                array('block_index' => $blockIndex),
                $logResult,
                $cacheResult
            );
    }

    /**
     * Returns asset related information from transaction.
     *
     * @param  string $txnHash      Transaction hash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return array('type' => ..., 'asset' => ..., 'quantity' => ..., 'type' => ...)
     * @throws UnexpectedValueException in case of unknown transaction type
     */
    public function getAssetInfoFromTxn($txnHash, $logResult = FALSE, $cacheResult = TRUE)
    {
        $data = $this->oRPC->execBitcoind(
            'getrawtransaction',
            array($txnHash),
            $logResult,
            $cacheResult
        );
        $aResult = $this->oRPC->execCounterpartyd(
            'get_tx_info',
            array('tx_hex' => $data),
            $logResult,
            $cacheResult
        );
        $data = $aResult[4];
        $type = hexdec(mb_substr($data, 0, 8));
        $assetName = mb_substr($data, 8, 16);
        $quantity = mb_substr($data, 24, 16);
        $assetId =
            new BigNumber(
                BigNumber::convertToBase10($assetName, 16)
            );
        if('00000000' != mb_substr($assetName, 0, 8)){
            $asset = 'A' . $assetId->getValue();
        }else{
            $asset = '';
            $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            do{
                $tmpAssetId = clone($assetId);
                $reminder = (int)$tmpAssetId->mod(26)->getValue();
                $asset .= $alphabet[$reminder];
                $assetId = $assetId->divide(26)->floor();
            }while($assetId->getValue() > 0);
            $asset = strrev($asset);
        }

        switch($type){
            case 0:
                $type = self::TXN_TYPE_SEND;
                break;
            case 20:
                $type = self::TXN_TYPE_ISSUANCE;
                break;
            default:
                throw new UnexpectedValueException('Unknown transaction type ' . $type);
        }

        $quantity =
            new BigNumber(
                BigNumber::convertToBase10($quantity, 16)
            );

        return array('asset' => $asset, 'quantity' => $quantity, 'type' => $type);
    }

    /**
     * Returns transactions from blocks filtered by passed assets.
     *
     * @param  array $aAssets        List of assets
     * @param  array $aBlockIndexes  List of block indexes
     * @param  bool  $logResult      Flag specifying to log result
     * @param  bool  $cacheResult    Flag specifying to cache result
     * @return array
     */
    public function getAssetsTxnsFromBlocks(
        array $aAssets,
        array $aBlockIndexes,
        $logResult = FALSE,
        $cacheResult = TRUE
    )
    {
        $aResult = array();
        $aBlocks = $this->oRPC->execCounterpartyd(
            'get_blocks',
            array('block_indexes' => $aBlockIndexes),
            $logResult,
            $cacheResult
        );
        foreach($aBlocks as $aBlock){
            if(empty($aBlock['_messages'])){
                continue;
            }
            foreach($aBlock['_messages'] as $aBlockMessage){
                if(
                    empty($aBlockMessage['bindings']) ||
                    empty($aBlockMessage['bindings'])
                ){
                    continue;
                }
                $aBindings = json_decode($aBlockMessage['bindings'], TRUE);
                if(!is_array($aBindings)){
                    continue;
                }
                $asset = '';
                if('order_matches' != $aBlockMessage['category']){
                    if(empty($aBindings['asset'])){
                        continue;
                    }
                    $asset = $aBindings['asset'];
                    if(!in_array($asset, $aAssets)){
                        continue;
                    }
                }elseif(
                    isset($aBindings['forward_asset']) &&
                    in_array($aBindings['forward_asset'], $aAssets)
                ){
                    // selling asset
                    $asset = $aBindings['forward_asset'];
                }elseif(
                    isset($aBindings['backward_asset']) &&
                    in_array($aBindings['backward_asset'], $aAssets)
                ){
                    // bying asset
                    $asset = $aBindings['backward_asset'];
                }else{
                    continue;
                }

                if(!isset($aResult[$asset])){
                    $aResult[$asset] = array();
                }
                if(isset($aBindings['quantity'])){
                    preg_match('/quantity":\s*(\d+)/', $aBlockMessage['bindings'], $aMatches);
                    $aBindings['quantity'] = $aMatches[1];
                }
                $aResult[$asset][] = array(
                    'bindings' => $aBindings,
                ) + $aBlockMessage;
            }
        }

        return $aResult;
    }



    /**
     * Returns wallets/assets balances.
     *
     * @param  array $aAssets          List of assets
     * @param  array $aWallets         List of wallets
     * @param  bool  $logResult        Flag specifying to log result
     * @param  bool  $cacheResult      Flag specifying to cache result
     * @return array
     */
    public function getBalances(
        array $aAssets = array(),
        array $aWallets = array(),
        $logResult = FALSE,
        $cacheResult = TRUE
    ){
        $aParams = array('filters' => array());
        if(sizeof($aWallets)){
            $aParams['filters'][] = array(
                'field' => 'address',
                'op'    => 'IN',
                'value' => $aWallets
            );
        }
        if(sizeof($aAssets)){
            $aParams['filters'][] = array(
                'field' => 'asset',
                'op'    => 'IN',
                'value' => $aAssets
            );
        }

        $aBalances = $this->oRPC->execCounterpartyd(
            'get_balances',
            $aParams,
            $logResult,
            $cacheResult
        );

        return $aBalances;
    }
}
