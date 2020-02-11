<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Cryptor;

/**
 * Class Cryptor
 *
 * $Cryptor = new Cryptor();
 * $a       = $Cryptor->setEncryptionKey('myPa$$worD123')->encrypt('megaSecretKey');
 * $b       = $Cryptor->setEncryptionKey('myPa$$worD123')->decrypt($a);
 *
 * @package AuroraBundle\Utils
 */
class Cryptor
{
    private $cipher  = 'AES-128-CTR';
    private $encryptionKey;
    private $options = 0;
    private $randomInitializationVector;

    public function __construct()
    {
        $this->randomInitializationVector = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        return $this;
    }

    public function setCipher($cipher = 'AES-128-CTR')
    {
        $this->cipher = $cipher;
        return $this;
    }

    public function setEncryptionKey($encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
        return $this;
    }

    public function encrypt(string $data)
    {
        $encrypted           = openssl_encrypt($data, $this->cipher, $this->encryptionKey, $this->options, $this->randomInitializationVector);
        $this->encryptionKey = null;
        return $encrypted;
    }

    public function decrypt(string $crypted)
    {
        $decrypted           = openssl_decrypt($crypted, $this->cipher, $this->encryptionKey, $this->options, $this->randomInitializationVector);
        $this->encryptionKey = null;
        return $decrypted;
    }
}