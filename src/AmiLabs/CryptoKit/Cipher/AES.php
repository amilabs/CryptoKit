<?php

namespace AmiLabs\CryptoKit\Cipher;

use AmiLabs\CryptoKit\ICipher;

/**
 * AES cipher implementation.
 */
class AES implements ICipher{
    /**
     * Generates salt, key and initialization vector.
     *
     * @param  string $password
     * @param  string $salt
     * @param  string $cipher
     * @return array  ['salt' => '...', 'key' => '...', 'iv' => '...']
     */
    public function generateKey($password, $salt, $cipher){
        /**
         * Number of rounds depends on the size of the AES in use:
         * - 3 rounds for 256 (2 rounds for the key, 1 for the IV)
         * - 3 rounds for 192 since it's not evenly divided by 128 bits
         * - 2 rounds for 128 (1 round for the key, 1 round for the IV)
         * @see https://github.com/mdp/gibberish-aes
         */
        $rounds = 3;
        if(preg_match('/\d+/', $cipher, $aMatches)){
            $bits = (int)$aMatches[0];
            switch($bits){
                case 128:
                    $rounds = 2;
                    break;
            }
        }

        $data00 = $password . $salt;
        $aMD5Hash = array();
        $aMD5Hash[0] = md5($data00, TRUE);
        $result = $aMD5Hash[0];
        for($i = 1; $i < $rounds; ++$i){
          $aMD5Hash[$i] = md5($aMD5Hash[$i - 1] . $data00, TRUE);
            $result .= $aMD5Hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32,16);
        $aResult = array(
            'salt' => $salt,
            'key'  => substr($salted, 0, 32),
            'iv'   => substr($salted, 32,16),
        );

        return $aResult;
    }
}
