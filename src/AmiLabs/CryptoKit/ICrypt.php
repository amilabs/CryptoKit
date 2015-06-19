<?php

namespace AmiLabs\CryptoKit;

/**
 * Encrypting/decrypting interface.
 */
interface ICrypt{
    /**
     * Generates and returns salt.
     *
     * @return string
     */
    public function generateSalt();

    /**
     * Encrypts data.
     *
     * @param  string $data
     * @param  string $cipher
     * @param  string $password
     * @param  string $iv        A non-NULL Initialization Vector
     * @return string
     */
    public function encrypt($data, $cipher, $password, $iv = '');

    /**
     * Decrypts data.
     *
     * @param  string $data
     * @param  string $cipher
     * @param  string $password
     * @param  string $iv        A non-NULL Initialization Vector
     * @return string
     */
    public function decrypt($data, $cipher, $password, $iv = '');
}
