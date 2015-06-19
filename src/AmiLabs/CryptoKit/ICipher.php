<?php

namespace AmiLabs\CryptoKit;

/**
 * Cipher interface.
 */
interface ICipher{
    /**
     * Generates salt, key and initialization vector.
     *
     * @param  string $password
     * @param  string $salt
     * @param  string $cipher
     * @return array  ['salt' => '...', 'key' => '...', 'iv' => '...']
     */
    public function generateKey($password, $salt, $cipher);
}
