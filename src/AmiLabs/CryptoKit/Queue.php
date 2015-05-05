<?php

namespace AmiLabs\CryptoKit;

use Exception;
use ErrorException;
use Deepelopment\Net\Request;
use Deepelopment\Net\RPC;
use AmiLabs\DevKit\Registry;
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
 *     <ul>
 *         <li>Setup next structure in config:
 * <code>
 * $aConfig['AmiLabs\\CryptoKit\\Queue'] = array(
 *     'queueURL'                      => 'https://.../queue.php',
 *     'signerURL'                     => 'https://.../signer.php',
 *     'hostId'                        => '...',
 *     'appKey'                        => '...',
 *     'privateKeyId'                  => '...',
 *     'decryptionKey'                 => '...',
 *     // Not critical for broadcasting to set flag to FALSE
 *     'throwImpossibilityToArchiveTx' => TRUE
 * );
 * </code>
 *         </li>
 *         <li>Code:
 * <code>
 * use AmiLabs\CryptoKit\Queue;
 *
 * $oQueue = new Queue;
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
     * @var \Deepelopment\Net\RPC\Client\JSON
     */
    protected $oRPC;

    /**
     * @var int
     */
    protected $queuedId;

    public function __construct(){
        $this->aConfig = Registry::useStorage('CFG')->get(get_class($this), FALSE);
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
                    if($this->aConfig['throwImpossibilityToArchiveTx']){
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
                if($this->aConfig['throwImpossibilityToArchiveTx']){
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
        /**
         * @var \Deepelopment\Net\RPC\Client\JSON
         */
        $this->oRPC = RPC::getLayer(
            'JSON',
            RPC::TYPE_CLIENT,
            Registry::useStorage('CFG')->get()
        );
        $this->oRPC->open($this->aConfig['queueURL']);
        $aResponse = $this->oRPC->execute(
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

        $oRequest = new Request(Registry::useStorage('CFG')->get());
        $oRequest->send($this->aConfig['signerURL']);

        $aResponse = $this->oRPC->execute(
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
        $aResponse = $this->oRPC->execute(
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
