<?php

namespace AmiLabs\CryptoKit;

require_once PATH_LIB . '/artemko7v/php-op_return/php-OP_RETURN.php';

/**
 * Transaction helper class.
 */
class TX {
    /**
     * Decodes OP_RETURN output from raw transaction hex.
     *
     * @param string $rawTXN  Raw transaction hex
     * @param boolean $asHex  Convert decoded value to hex if true
     * @return string
     */
    public static function getDecodedOpReturn($rawTXN, $asHex = true)
    {
        $aTXNunpacked = coinspark_unpack_raw_txn($rawTXN);
        $res = '';
        foreach($aTXNunpacked['vout'] as $aOut){
            if(isset($aOut['scriptPubKey']) && (strpos($aOut['scriptPubKey'], '6a') === 0)){
                $res = pack('H*', substr($aOut['scriptPubKey'], 4));
            }
        }
        return ($asHex) ? reset(unpack('H*', $res)) : $res;
    }

    /**
     * Adds OP_RETURN output to raw transaction hex.
     *
     * @param string $rawTXN    Source raw transaction string
     * @param string $metadata  Metadata to iclude in transaction (40 bytes max)
     * @return string
     */
    public static function addOpReturnOutput($rawTXN, $metadata)
	{
		$aTXNunpacked = coinspark_unpack_raw_txn($rawTXN);
		$aTXNunpacked['vout'][] = array(
			'value' => 0,
			'scriptPubKey' => '6a' . reset(unpack('H*', chr(strlen($metadata)) . $metadata)),
		);
		return coinspark_pack_raw_txn($aTXNunpacked);
	}

    /**
     * Adds custom OP_HASH output. Not recommended to use because it eats memory.
     *
     * @param string $rawTXN   Source raw transaction string
     * @param type $hexString  32 bytes hex string (deadbeefcafe0000000000000000000000000001)
     * @return string
     */
    public static function addOpHashOutput($rawTXN, $hexString)
    {
        $aTXNunpacked = coinspark_unpack_raw_txn($rawTXN);
        $aTXNunpacked['vout'][]=array(
            'value' => 0.000078,
            'scriptPubKey' => '76a914' . $hexString . '88ac'
        );
        return coinspark_pack_raw_txn($aTXNunpacked);
	}

    /**
     * Decodes raw transaction data into array.
     *
     * @param string $rawTXN
     * @return array
     */
    public static function decodeTransaction($rawTXN)
    {
        return coinspark_unpack_raw_txn($rawTXN);
    }

    /**
     * Store data in multisig output.
     *
     * @param type $rawTXN
     * @param type $hexString
     * @return type
     */
    public static function addMultisigDataOutput($rawTXN, $data)
    {
        $hexString = reset(unpack('H*', $data));
        $dataLength = strlen($hexString);
        if($dataLength <= 196){
            $hexString = str_pad($hexString, 196, '0', STR_PAD_LEFT);
        }else{
           // data is too big
           // more outputs: todo
        }
        $hexPart1 = substr($hexString, 0, 64);
        $hexPart2 = substr($hexString, 64, 66);
        $hexPart3 = substr($hexString, 130, 66);

        $hexString = reset(unpack('H*', chr($dataLength))) . $hexPart1 . '21' . $hexPart2 . '21' . $hexPart3;
        $aTXNunpacked = coinspark_unpack_raw_txn($rawTXN);
        $last = array_pop($aTXNunpacked['vout']);
        $aTXNunpacked['vout'][]=array(
            'value' => 0.000078,
            'scriptPubKey' => '5121' . $hexString . '53ae'
        );
        $aTXNunpacked['vout'][]=$last;
        return coinspark_pack_raw_txn($aTXNunpacked);
	}

    /**
     * Returns hash of a signed transaction without broadcast.
     *
     * @param string $rawHexData  Signed tx raw hex
     * @return string
     */
    public static function calculateTxHash($rawHexData)
    {
        $reversedHash = hash('sha256', hash('sha256', pack("H*", trim($rawHexData)), true));
        return reset(unpack('H*', strrev(pack('H*', $reversedHash))));
    }
}
