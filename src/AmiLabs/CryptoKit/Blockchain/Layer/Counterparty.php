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

class Counterparty implements ILayer
{
    const LAST_BLOCK_INFO_ATTEMPTS = 7;
    const LAST_BLOCK_INFO_WAIT     = 2000000; // 2.0 sec
    const MINER_FEE_VALUE = 30000;

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
        $oLogger = Logger::get('check-servers');
        if(isset($aConfig['counterblockd'])){
            $address = $aConfig['counterblockd']['address'];
            $aContextOptions = array('http' => array('timeout' => 5), 'ssl'  => array('verify_peer' => FALSE, 'verify_peer_name' => FALSE));
            $state = @file_get_contents($address, FALSE, stream_context_create($aContextOptions));
            $result = ($state && (substr($state, 0, 1) == '{') && ($aState = json_decode($state, TRUE)) && is_array($aState) && isset($aState['counterparty-server']) && ('OK' == $aState['counterparty-server']));
            $oLogger->log($result ? ('OK: ' . $address . ' is UP and RUNNING, using as primary') : ('ERROR: ' . $address . ' is DOWN, skipping'));
        }else{
            // Can not check state without Counterblock
            $oLogger->log('SKIP: No counterblock information in RPC config');
        }
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

        return $aState;
    }

    /**
     * Returns list of block transactions.
     *
     * @param  string $blockHash
     * @param  bool   $logResult    Flag specifying to log result
     * @param  bool   $cacheResult  Flag specifying to cache result
     * @return mixed
     */
    public function getBlock($blockHash, $logResult = false, $cacheResult = true)
    {
        return
            $this->getRPC()->exec(
                'bitcoind',
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
    public function getBlockInfo($blockIndex, $logResult = FALSE, $cacheResult = TRUE)
    {
        return
            $this->getRPC()->exec(
                'counterpartyd',
                'get_block_info',
                array('block_index' => $blockIndex),
                $logResult,
                $cacheResult
            );
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
    public function getRawTransaction($txHash, $extended = FALSE, $logResult = FALSE, $cacheResult = TRUE)
    {
        return
            $this->getRPC()->exec(
                'bitcoind',
                'getrawtransaction',
                array($txHash, (int)!!$extended),
                $logResult,
                $cacheResult
            );
    }

    /**
     * Returns newest unconfirmed transactions.
     *
     * @param bool $logResult  Flag specifying to log result
     * @return array
     */
    public function getLastTransactions($logResult = FALSE)
    {
        return
            $this->getRPC()->exec(
                'bitcoind',
                'getrawmempool',
                array(),
                FALSE, // $logResult,
                FALSE  // Never cached
            );
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
            /*
            // Commented because wrong asset name parsed from tx
            case 50:
                $type = self::TXN_TYPE_DIVIDENDS;
                break;
            */
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
    public function getAssetTxsFromBlocks(
        array $aAssets,
        array $aBlockIndexes,
        $logResult = FALSE,
        $cacheResult = TRUE
    )
    {
        // $assets = implode('-', $aAssets);###
        $aResult = array();
        $aBlocks = $this->getRPC()->exec(
            'counterpartyd',
            'get_blocks',
            array('block_indexes' => $aBlockIndexes),
            $logResult,
            $cacheResult
        );
        foreach($aBlocks as $aBlock){
            if(empty($aBlock['_messages'])){
                continue;
            }
            ### {
            /*
            $aDecoded = $aBlock['_messages'];
            foreach($aDecoded as $index => $aDec){
                if(isset($aDec['bindings'])){
                    $aDecoded[$index]['bindings'] = json_decode($aDec['bindings'], TRUE);
                }
            }
            file_put_contents("{$assets}.tx.log", print_r($aDecoded, TRUE), FILE_APPEND);###
            unset($aDecoded);
            */
            ### }
            foreach($aBlock['_messages'] as $aBlockMessage){
                // if(in_array($aBlockMessage['block_index'], array(311579, 323002, 331597))){file_put_contents('block.log', print_r($aBlockMessage, TRUE), FILE_APPEND);}###
                if(empty($aBlockMessage['bindings'])){
                    continue;
                }
                $aBindings = json_decode($aBlockMessage['bindings'], TRUE);
                if(!is_array($aBindings)){
                    continue;
                }
                switch($aBlockMessage['category']){
                    case 'order_matches':
                        if(
                            'update' != $aBlockMessage['command'] &&
                            (
                                !isset($aBindings['forward_asset']) ||
                                !isset($aBindings['backward_asset']) ||
                                (
                                    !in_array($aBindings['forward_asset'], $aAssets) &&
                                    !in_array($aBindings['backward_asset'], $aAssets)
                                )
                            )
                        ){
                            continue 2;
                        }
                        break; // case 'order_matches'

                    case 'orders':
                        if(
                            (
                                'insert' == $aBlockMessage['command'] &&
                                isset($aBindings['give_asset']) &&
                                !in_array($aBindings['give_asset'], $aAssets)
                            )
                        ){
                            continue 2;
                        }
                        break; // case 'orders'

                    case 'dividends':
                        ### {
                        /*
                        if(
                            'valid' == $aBindings['status'] ||
                            in_array($aBindings['dividend_asset'], $aAssets) ||
                            in_array($aBindings['asset'], $aAssets)
                        ){
                            static $ass;
                            $newAsset = '';
                            if(!in_array($aBindings['dividend_asset'], $aAssets)){
                                $newAsset = $aBindings['dividend_asset'];
                            }elseif(!in_array($aBindings['asset'], $aAssets)){
                                $newAsset = $aBindings['asset'];
                            }
                            if('' !== $newAsset && empty($ass[$newAsset])){
                                $ass[$newAsset] = TRUE;
                                echo "DIVIDENDS: New asset found: {$newAsset}\n";
                                $aBlockMessage['bindings'] = $aBindings;
                                print_r($aBlockMessage);
                            }
                        }
                        */
                        ### }
                        if(
                            !isset($aBindings['quantity_per_unit']) ||
                            'valid' != $aBindings['status'] ||
                            !in_array($aBindings['dividend_asset'], $aAssets)
                        ){
                            continue 2;
                        }
                        // print_r($aBlockMessage);###
                        break; // case 'dividends'

                    default:
                        if(
                            empty($aBindings['asset']) ||
                            !in_array($aBindings['asset'], $aAssets)
                        ){
                            continue 2;
                        }
                }
                // 64-bit PHP integer hack
                if(FALSE && $this->is32bit){
                    foreach(
                        array(
                            'quantity',
                            'give_quantity',
                            'give_remaining',
                            'get_quantity',
                            'get_remaining',
                            'forward_quantity',
                            'backward_quantity'
                        ) as $key
                    ){
                        if(isset($aBindings[$key])){
                            preg_match('/' . $key . '":\s*(-?\d+)[^0-9]/', $aBlockMessage['bindings'], $aMatches);
                            $aBindings[$key] = $aMatches[1];
                        }
                    }
                }
                $aBlockMessage['bindings'] = $aBindings;
                $aResult[] = $aBlockMessage;
            }
        }

        return $aResult;
    }

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

        $aBalances = $this->getRPC()->exec(
            'counterpartyd',
            'get_balances',
            $aParams + $aExtraParams,
            $logResult
        );

        return $aBalances;
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
        return
            $this->getRPC()->exec(
                'counterpartyd',
                'create_send',
                array(
                    "asset"                     => $asset,
                    "source"                    => $source,
                    "destination"               => $destination,
                    "quantity"                  => (int)$amount,
                    "allow_unconfirmed_inputs"  => true,
                    "encoding"                  => "multisig",
                    "pubkey"                    => $aPublicKeys,
                    "fee_per_kb"                => self::MINER_FEE_VALUE
                ),
                $logResult
            );
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
        $result =
            $this->getRPC()->exec(
                'bitcoind',
                'signrawtransaction',
                array(
                    $rawData,
                    array(),
                    array($privateKey)
                ),
                $logResult
            );
        if(isset($result['hex'])){
            $result = $result['hex'];
        }
        $result = (string)$result;

        return $result;
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
        $result = $this->getRPC()->execBitcoind(
            'sendrawtransaction',
            array($rawData),
            $logResult
        );

        return $result;
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
        $result = $this->getRPC()->execBitcoind(
            'decoderawtransaction',
            array($rawData),
            $logResult,
            $cacheResult
        );

        return $result;
    }

    /**
     * Checks if bitcoind::getrawtransaction response can be stored in cache.
     *
     * @param mixed $response  Bitcoind response
     * @return bool
     * @todo   Cover by unit tests
     */
    public function validateGetRawTransactionCache($response)
    {
        $result = FALSE;
        if(is_array($response) && isset($response['blockhash'])){
            // Valid extended tx info
            $result = TRUE;
        }
        if(is_string($response) && strlen($response) && ($response[0] === '0')){
            // Valid raw tx
            $result = TRUE;
        }

        return $result;
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
            $this->addCacheValidators();
        }
        return $this->oRPC;
    }

    /**
     * Adds RPC response validators.
     */
    protected function addCacheValidators()
    {
        $this->getRPC()->addCacheRule('bitcoind', 'getrawtransaction', array($this, 'validateGetRawTransactionCache'));
    }

    /**
     * Returns wallets/assets balances from database.
     *
     * @param  array $aAssets   List of assets
     * @param  array $aWallets  List of wallets
     * @return array
     * @todo   Use PDO prepared statements
     */
    /*
    public function getBalancesFromDB(array $aAssets = array(),array $aWallets = array())
    {
        $this->connectToDB();

        $where = '';
        if(sizeof($aWallets)){
            $where .= " AND address IN ('" . implode("', '", $aWallets) . "')";
        }
        if(sizeof($aAssets)){
            $where .= " AND asset IN ('" . implode("', '", $aAssets) . "')";
        }
        if('' != $where){
            $where = "WHERE 1" . $where;
        }
        $oStmt = $this->oDB->query(
            "SELECT * " .
            "FROM balances " .
            $where
        );

        return $oStmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    */

    /**
     * Connects to counterparty database.
     *
     * @return void
     */
    /*
    protected function connectToDB()
    {
        if(is_object($this->oDB)){
            return;
        }
        $aDB = Registry::useStorage('CFG')->get('db');
        $aDB = $aDB['counterpartyd'];
        $this->oDB = new \PDO(
            $aDB['dsn'],
            $aDB['username'],
            $aDB['password'],
            $aDB['options']
        );
    }
    */
}
