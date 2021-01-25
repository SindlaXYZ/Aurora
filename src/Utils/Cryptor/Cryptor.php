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

    public function setCipher($cipher = 'AES-128-CTR'): Cryptor
    {
        $this->cipher = $cipher;
        return $this;
    }

    public function setEncryptionKey($encryptionKey): Cryptor
    {
        $this->encryptionKey = $encryptionKey;
        return $this;
    }

    /**
     * @param string $data
     * @return string (base64 of "encrypted key::initialization vector"
     */
    public function encrypt(string $data): string
    {
        $encrypted           = openssl_encrypt($data, $this->cipher, $this->encryptionKey, $this->options, $this->randomInitializationVector);
        $this->encryptionKey = null;
        return "{$encrypted}::{$this->randomInitializationVector}";
    }

    /**
     * @param string $encryptedBase64 (base64 of "encrypted key::initialization vector"
     * @return string
     */
    public function decrypt(string $encryptedBase64): string
    {
        [$data, $vector] = explode('::', base64_decode($encryptedBase64));

        $decrypted           = openssl_decrypt($data, $this->cipher, $this->encryptionKey, $this->options, $vector);
        $this->encryptionKey = null;
        return (string)$decrypted;
    }
}