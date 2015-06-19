<?php

namespace AmiLabs\CryptoKit\Crypt;

use AmiLabs\CryptoKit\ICrypt;

/**
 * Encrypting/decrypting OpenSSL implementation.
 */
class OpenSSL implements ICrypt{
    /**
     * Generates and returns salt.
     *
     * @return string
     */
    public function generateSalt(){
        $salt = openssl_random_pseudo_bytes(8);

        return $salt;
    }

    /**
     * Encrypts data.
     *
     * @param  string $data
     * @param  string $cipher    {@see http://php.net/manual/en/function.openssl-get-cipher-methods.php}
     * @param  string $password
     * @param  string $iv        A non-NULL Initialization Vector
     * @return string
     */
    public function encrypt($data, $cipher, $password, $iv = ''){
        $encrypted = openssl_encrypt(
            $data,
            $cipher,
            $password,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $encrypted;
    }

    /**
     * Decrypts data.
     *
     * @param  string $data
     * @param  string $cipher    {@see http://php.net/manual/en/function.openssl-get-cipher-methods.php}
     * @param  string $password
     * @param  string $iv        A non-NULL Initialization Vector
     * @return string
     */
    public function decrypt($data, $cipher, $password, $iv = ''){
        $decrypted = openssl_decrypt(
            $data,
            $cipher,
            $password,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted;
    }
}
