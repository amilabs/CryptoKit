<?php

namespace AmiLabs\CryptoKit;

use Exception;
use ErrorException;
use AmiLabs\DevKit\Registry;
use AmiLabs\CryptoKit\RPC;
use AmiLabs\CryptoKit\TX;

/**
 * Tx signing service queue implementation.
 *
 * <ul>
 *     <li>Direct broadcasting:
 * <code>
 * use AmiLabs\CryptoKit\Queue;
 *
 * $oQueue = new Queue;
 * try{
 *     $txHash = $oQueue->broadcastTx($rawTxData, $privateKey);
 * }catch(Exception $oException){
 *     // ...
 * }
 * </code>
 *     </li>
 *     <li>Broadcasting using signing service:
 * <code>
 * use AmiLabs\CryptoKit\Queue;
 *
 * $oQueue = new Queue($privateKeyId, $decryptionKey, [$hostId], [$appKey]);
 * try{
 *     $txHash = $oQueue->broadcastTx($rawTxData);
 * }catch(Exception $oException){
 *     // ...
 * }
 * </code>
 *         </li>
 *     </ul>
 *     </li>
 * </ul>
 */
class Queue{
    /**
     * Queue configuration
     *
     * @var mixed
     */
    protected $aConfig = FALSE;
    /**
     * RPC Engine
     *
     * @var \AmiLabs\CryptoKit\RPC
     */
    protected $oRPC;

    /**
     * @var int
     */
    protected $queuedId;

    /**
     * Constructor.
     *
     * @param string $privateKeyId                   Private Key ID
     * @param string $decryptionKey                  Decryption Key for provided PK ID
     * @param string $hostId                         HOST ID
     * @param string $appKey                         Application Key
     * @param bool   $throwImpossibilityToArchiveTx  Throw exception on failure
     * @todo Throw an exception if obligatory parameters are missing
     * @todo Change name of config params
     */
    public function __construct($privateKeyId = FALSE, $decryptionKey = FALSE, $hostId = FALSE, $appKey = FALSE, $throwImpossibilityToArchiveTx = TRUE){
        if($privateKeyId){
            $this->aConfig = array(
                'host_id'  => $hostId,
                'app_key'  => $appKey,
                'pk_id'    => $privateKeyId,
                'dec_key'  => $decryptionKey,
                'throw_ex' => $throwImpossibilityToArchiveTx
            );
        }
        /**
         * @var \AmiLabs\CryptoKit\RPC
         */
        $this->oRPC = RPC();
    }

    /**
     * Broadcasts tx.
     *
     * @param  string $txData
     * @param  string $privateKey  Optional if signing service is used
     * @return string  Tx hash
     */
    public function broadcastTx($txData, $privateKey = ''){
        $oBlockChain = BlockchainIO::getLayer();
        $useQueue = is_array($this->aConfig);
        $result =
            $useQueue
                ? $this->signTxUsingQueueService($txData, $privateKey)
                : $oBlockChain->signRawTx($txData, $privateKey);
        try{
            $oBlockChain->sendRawTx($result);
        }catch(Exception $oException){
            if($useQueue){
                try{
                    $this->archiveTx('F', $oException->getMessage());
                }catch(Exception $oException){
                    if($this->aConfig['throw_ex']){
                        throw $oException;
                    }
                }
            }
            throw $oException;
        }
        $txHash = TX::calculateTxHash($result);
        if($useQueue){
            try{
                $this->archiveTx('S', 'Broadcasted successfully', $txHash);
            }catch(Exception $oException){
                if($this->aConfig['throw_ex']){
                    throw $oException;
                }
            }
        }

        return $txHash;
    }

    /**
     * Signs tx using queue service.
     *
     * @param  string $txData
     * @param  string $pkHash
     * @return string
     * @throws ErrorException
     */
    protected function signTxUsingQueueService($txData){
        $aResponse =
            $this->oRPC->exec(
                'mr-queue',
                'enqueue',
                array(
                    'host_id' => $this->aConfig['hostId'],
                    'app_key' => $this->aConfig['appKey'],
                    'pk_id'   => $this->aConfig['privateKeyId'],
                    'dec_key' => $this->aConfig['decryptionKey'],
                    'tx_data' => $txData
                )
            );

        if(!is_array($aResponse)){
            throw new ErrorException("Cannot parse response");
        }
        if('OK' !== $aResponse['status']){
            throw new ErrorException(
                sprintf(
                    "Bad response:\n%s",
                    print_r($aResponse, TRUE)
                )
            );
        }
        $this->queuedId = $aResponse['id'];

        $aResponse =
            $this->oRPC->exec(
                'mr-queue',
                'get',
                array(
                    'host_id' => $this->aConfig['hostId'],
                    'app_key' => $this->aConfig['appKey']
                )
            );
        if(!is_array($aResponse)){
            throw new ErrorException("Cannot parse response");
        }
        $found = FALSE;
        foreach($aResponse as $aTx){
            if($this->queuedId == $aTx['id']){
                if('S' == $aTx['status']){
                    $found = TRUE;
                    break;
                }else{
                    throw new ErrorException('Tx not signed');
                }
            }
        }
        if(!$found){
            throw new ErrorException('Signed tx not found');
        }

        return $aTx['signed_tx_data'];
    }

    /**
     * Archives tx.
     *
     * @param  string $status
     * @param  string $comment
     * @param  string $txHash
     * @return void
     */
    protected function archiveTx($status, $comment, $txHash = ''){
        $aResponse = $this->oRPC->exec(
            'mr-queue',
            'archive',
            array(
                'host_id' => $this->aConfig['hostId'],
                'app_key' => $this->aConfig['appKey'],
                'txs' => array(
                    array(
                        'id'      => $this->queuedId,
                        'status'  => $status,
                        'comment' => $comment,
                        'tx_hash' => $txHash
                    )
                )
            )
        );
        if(!is_array($aResponse)){
            throw new ErrorException("Cannot parse response");
        }
        if(1 != $aResponse['qty']){
            throw new ErrorException(
                sprintf(
                    "Bad response:\n%s",
                    print_r($aResponse, TRUE)
                )
            );
        }
    }
}
