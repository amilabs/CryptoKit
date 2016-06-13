<?php

namespace AmiLabs\CryptoKit\Blockchain\Layer;

use Exception;
use RuntimeException;
use UnexpectedValueException;
use AmiLabs\CryptoKit\Blockchain\ILayer;
use AmiLabs\CryptoKit\RPC;
use Moontoast\Math\BigNumber;
use AmiLabs\DevKit\Logger;
use AmiLabs\DevKit\Registry;

class Ethereum implements ILayer
{
    /**
     * RPC execution object
     *
     * @var \AmiLabs\CryptoKit\RPC
     */
    protected $oRPC;

    /**
     * Flag specifying that PHP integer is 32bit only
     *
     * @var bool
     */
    protected $is32bit;

    /**
     * Database connection object
     *
     * @var \PDO
     */
    // protected $oDB;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->is32bit = PHP_INT_MAX <= 2147483647;
    }

    /**
     * Checks if Counterparty server is up and running.
     *
     * @param  array $aConfig  Server configuration
     * @return bool
     */
    public function checkServerConfig(array $aConfig)
    {
        $result = TRUE;
        /*
        $oLogger = Logger::get('check-eth-servers');
        if(isset($aConfig['eth-service'])){
            $address = $aConfig['eth-service']['address'];
            $aContextOptions = array('http' => array('timeout' => 5), 'ssl'  => array('verify_peer' => FALSE, 'verify_peer_name' => FALSE));
            $state = @file_get_contents($address, FALSE, stream_context_create($aContextOptions));
            $result = ($state && (substr($state, 0, 1) == '{') && ($aState = json_decode($state, TRUE)) && is_array($aState) && isset($aState['counterparty-server']) && ('OK' == $aState['counterparty-server']));
            $oLogger->log($result ? ('OK: ' . $address . ' is UP and RUNNING, using as primary') : ('ERROR: ' . $address . ' is DOWN, skipping'));
        }else{
            // Can not check state without Counterblock
            $oLogger->log('SKIP: No counterblock information in RPC config');
        }
        */
        return $result;
    }

    /**
     * Returns some operational parameters for the server.
     *
     * @param  bool $ignoreLastBlockInfo  Flag specifying to ignore last block info if not available
     * @param  bool $logResult            Flag specifying to log result
     * @return array
     * @throws RuntimeException  optionally, if last block info not available
     */
    public function getServerState($ignoreLastBlockInfo = FALSE, $logResult = FALSE)
    {
        $aState = array('last_block' => 0);

        /*
        for($attempt = 0; $attempt < self::LAST_BLOCK_INFO_ATTEMPTS; ++$attempt){
            $aState = $this->getRPC()->exec(
                'counterpartyd',
                'get_running_info',
                array(),
                $logResult,
                FALSE
            );
            if($ignoreLastBlockInfo || !is_null($aState['last_block'])){
                break;
            }
            usleep(self::LAST_BLOCK_INFO_WAIT);
        }
        if(!$ignoreLastBlockInfo && is_null($aState['last_block'])){
            throw new RuntimeException('Blockchain: cannot get last block info');
        }
         */

        return $aState;
    }

    /**
     * Returns wallets/assets balances.
     *
     * @return string
     */
    public function getBalancesServiceName(){
        return 'eth-service';
    }

    /**
     * Returns list of block transactions.
     *
     * @param  string $blockHash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlock($blockHash, $logResult = FALSE, $cacheResult = TRUE)
    {
        $result = $this->getRPC()->exec('eth-service', 'getBlock', array('blockNumber' => $blockHash), $logResult);
        return $result;
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
        return $this->getBlock($blockIndex, $logResult, $cacheResult);
    }

    /**
     * Returns transaction raw hex with (or without) extended info.
     *
     * @param string $txHash     Transaction hash
     * @param bool $onlyHex      Return only tx raw hex if set to true
     * @param bool $logResult    Flag specifying to log result
     * @param bool $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getRawTransaction($txHash, $extended = FALSE, $logResult = FALSE, $cacheResult = TRUE){
        return array();
    }

    /**
     * Returns newest unconfirmed transactions.
     *
     * @param bool $logResult  Flag specifying to log result
     * @return array
     */
    public function getLastTransactions($logResult = FALSE){
        return array();
    }

    /**
     * Returns asset related information from transaction.
     *
     * @param  string $txHash       Transaction hash or raw data
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
     * @throws UnexpectedValueException in case of unknown transaction type
     * @todo   Count correct quantity for BTC txs
     */
    public function getAssetInfoFromTx(
        $txHash,
        $hashPassed = TRUE,
        $logResult = FALSE,
        $cacheResult = TRUE
    )
    {
        $aResult = array();

        /*
        if($hashPassed){
            $aData =
                $this->getRawTransaction($txHash, TRUE, $logResult, $cacheResult);
            $rawData = $aData['hex'];
        }else{
            $rawData = $txHash;
        }

        try{
            $aResult = $this->getRPC()->exec(
                'counterpartyd',
                'get_tx_info',
                array(
                    'tx_hex'      => $rawData,
                    ### 'block_index' => $aBlock['height']
                ),
                $logResult,
                $cacheResult
            );
        }catch(Exception $oException){
            if(
                -1 == $oException->getCode() &&
                FALSE !== strpos($oException->getMessage(), 'HTTP code: 500')
            ){
                // Not counterparty tx
                $aDecodedTx = $this->decodeRawTx($rawData);
                $source = '';
                $destination = '';
                $quantity = 0;
                foreach($aDecodedTx['vout'] as $aVOut){
                    if(
                        isset($aVOut['scriptPubKey']['type']) &&
                        isset($aVOut['scriptPubKey']['addresses']) &&
                        in_array($aVOut['scriptPubKey']['type'], array('pubkeyhash', 'multisig')) &&
                        isset($aVOut['scriptPubKey']['addresses'])
                    ){
                        $qty = sizeof($aVOut['scriptPubKey']['addresses']);
                        if($qty > 1){
                            $address =
                                $qty . '_' .
                                implode('_', $aVOut['scriptPubKey']['addresses']) .
                                '_' . $qty;
                        }else{
                            $address = $aVOut['scriptPubKey']['addresses'][0];
                        }
                        if('' == $destination){
                            $destination = $address;
                        }else{
                            $source = $address;
                        }
                    }
                    // $quantity += $aVOut['value'];
                }
                $aResult = array(
                    'source'      => $source,
                    'destination' => $destination,
                    'asset'       => 'BTC',
                    'quantity'    => $quantity,
                    'type'        => self::TXN_TYPE_SEND
                );

                return $aResult;
            }
            throw $oException;
        }
        $data = $aResult[4];
        $type = hexdec(mb_substr($data, 0, 8));
        $assetName = mb_substr($data, 8, 16);
        $quantity = mb_substr($data, 24, 16);
        $assetId =
            new BigNumber(
                BigNumber::convertToBase10($assetName, 16)
            );
        // if('00000000' != mb_substr($assetName, 0, 8)){
        if('0' != mb_substr($assetName, 0, 1)){
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
                throw new UnexpectedValueException('Unknown/unsupported transaction type ' . $type);
        }

        $quantity =
            new BigNumber(
                BigNumber::convertToBase10($quantity, 16)
            );

        $aResult = array(
            'source'      => $aResult[0],
            'destination' => $aResult[1],
            'asset'       => $asset,
            'quantity'    => $quantity->getValue(),
            'type'        => $type
        );
        */
        return $aResult;
    }

    /**
     * Returns transactions from blocks filtered by passed asset.
     *
     * @param  array  $aAssets        List of assets
     * @param  array  $aBlockIndexes  List of block indexes
     * @param  bool   $logResult      Flag specifying to log result
     * @param  bool   $cacheResult    Flag specifying to cache result
     * @return array
     */
    public function getAssetTxsFromBlocks(array $aAssets, array $aBlockIndexes, $logResult = FALSE, $cacheResult = TRUE){
        throw new Exception('Method is not supported');
    }

    /**
     * Creates specified tx sending amount of asset from source
     * to destination and returns raw tx data.
     *
     * @param  string $source       Source address
     * @param  string $destination  Destination address
     * @param  string $asset        Asset name
     * @param  int    $amount       Amount (in satoshi)
     * @param  array  $aPublicKeys  List of public keys of all addresses
     * @param  bool   $logResult    Flag specifying to log result
     * @return string
     */
    public function send($source, $destination, $asset, $amount, array $aPublicKeys = array(), $logResult = TRUE)
    {
        return $this->getRPC()->exec('eth-service', 'createSendTx', array($source, $destination, $asset, $amount), $logResult);
    }

    /**
     * Signs raw tx.
     *
     * @param  string $rawData
     * @param  string $privateKey
     * @param  bool   $logResult    Flag specifying to log result
     * @return string
     * @todo   Cover by unit tests
     */
    public function signRawTx($rawData, $privateKey, $logResult = TRUE)
    {
        return $this->getRPC()->exec('eth-service', 'signTx', array($rawData, $privateKey), $logResult);
    }

    /**
     * Sends raw tx.
     *
     * @param  string $rawData
     * @param  bool   $logResult  Flag specifying to log result
     * @return string
     * @todo   Cover by unit tests
     */
    public function sendRawTx($rawData, $logResult = TRUE){
        return $this->getRPC()->exec('eth-service', 'sendTx', array($rawData), $logResult);
    }

    /**
     * Decodes raw tx.
     *
     * @param  string $rawData
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return array
     */
    public function decodeRawTx($rawData, $logResult = FALSE, $cacheResult = TRUE)
    {
        $result = array();

        /*
        $result = $this->getRPC()->execBitcoind(
            'decoderawtransaction',
            array($rawData),
            $logResult,
            $cacheResult
        );
        */

        return $result;
    }

    /**
     * Returns number of transaction confirmations.
     *
     * @param string $txHash     Transaction hash
     * @param bool $onlyHex      Return only tx raw hex if set to true
     * @param bool $logResult    Flag specifying to log result
     * @return mixed
     */
    public function getTxConfirmations($txHash, $logResult = FALSE){
        $result = 0;
        // Current block number - Tx block number
        return $result;
    }


    /**
     * Returns wallets/assets balances.er
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
    ){
        return array();
    }

    /**
     * Returns addresse balances of blockchain native coin (BTC, ETH, etc).
     *
     * @param string $aAddresses    Addresses list
     * @param bool   $logResult     Flag specifying to log result
     * @param bool   $cacheResult   Flag specifying to cache result
     * @return array
     */
    public function getFuelBalance($aAddresses, $logResult = FALSE){
        $aResult = array('ETH' => 0);
        return $aResult;
    }

    /**
     * Checks if bitcoind::getrawtransaction response can be stored in cache.
     *
     * @param mixed $response  Bitcoind response
     * @return bool
     * @todo   Cover by unit tests
     */
    public function validateGetRawTransactionCache($response){
        throw new Exception('Method is not supported');
    }

    /**
     * Creates new RPC object, or uses existing one.
     *
     * @return \AmiLabs\CryptoKit\RPC
     */
    protected function getRPC()
    {
        if(is_null($this->oRPC)){
            $this->oRPC = new RPC;
        }
        return $this->oRPC;
    }
}
