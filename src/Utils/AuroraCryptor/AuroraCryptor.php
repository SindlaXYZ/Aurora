<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCryptor;

/**
 * $auroraCryptor = new AuroraCryptor();
 * $a       = $Cryptor->setEncryptionKey('myPa$$worD123')->encrypt('megaSecretKey');
 * $b       = $Cryptor->setEncryptionKey('myPa$$worD123')->decrypt($a);
 */
class AuroraCryptor
{
    private string $cipher = 'AES-128-CTR';
    private ?string $encryptionKey = null;
    private int $options = 0;

    public function setCipher(string $cipher = 'AES-128-CTR'): self
    {
        $this->cipher = $cipher;

        $length = openssl_cipher_iv_length($this->cipher);

        if (false === $length) {
            throw new \RuntimeException(sprintf('Cipher "%s" is not supported.', $this->cipher));
        }

        if ($length < 1) {
            throw new \RuntimeException(sprintf('Cipher "%s" requires a positive initialization vector length.', $this->cipher));
        }

        return $this;
    }

    public function setEncryptionKey(string $encryptionKey): self
    {
        $this->encryptionKey = $encryptionKey;
        return $this;
    }

    /**
     * @return string (base64 of "encrypted key::initialization vector"
     */
    public function encrypt(string $data): string
    {
        $length = openssl_cipher_iv_length($this->cipher);

        if (false === $length) {
            throw new \RuntimeException(sprintf('Cipher "%s" is not supported.', $this->cipher));
        }

        if ($length < 1) {
            throw new \RuntimeException(sprintf('Cipher "%s" requires a positive initialization vector length.', $this->cipher));
        }

        $vector = random_bytes($length);

        if (null === $this->encryptionKey) {
            throw new \RuntimeException('Encryption key must be provided before encrypting data.');
        }

        $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, $this->options, $vector);

        if (false === $encrypted) {
            $this->encryptionKey = null;
            throw new \RuntimeException('Unable to encrypt the provided data.');
        }

        $this->encryptionKey = null;

        return base64_encode($encrypted . '::' . $vector);
    }

    /**
     * @param string $encryptedBase64 (base64 of "encrypted key::initialization vector"
     */
    public function decrypt(string $encryptedBase64): string
    {
        $decoded = base64_decode($encryptedBase64, true);

        if (false === $decoded) {
            $this->encryptionKey = null;
            throw new \InvalidArgumentException('Encrypted payload must be valid base64.');
        }

        $parts = explode('::', $decoded, 2);

        if (2 !== count($parts)) {
            $this->encryptionKey = null;
            throw new \InvalidArgumentException('Encrypted payload is missing the initialization vector.');
        }

        [$data, $vector] = $parts;

        if (null === $this->encryptionKey) {
            throw new \RuntimeException('Encryption key must be provided before decrypting data.');
        }

        $decrypted = openssl_decrypt($data, $this->cipher, $this->encryptionKey, $this->options, $vector);
        $this->encryptionKey = null;

        return (string) $decrypted;
    }

    /**
     * Computes the FNV-1a hash of a string using 64-bit arithmetic
     * This method works both on 32-bit and 64-bit systems
     * This method may be faster than the sha256To64Bit() but may not be as secure and might produce collisions
     */
    function fnv1a64(string $data): string
    {
        // Offset basis value for FNV-1a on 64 bits
        $offsetBasis = gmp_init('14695981039346656037');

        // FNV prime constant for 64 bits (0x100000001b3 in hexadecimal)
        $fnvPrime = gmp_init('1099511628211');

        // Initialize hash with the offset value
        $hash = $offsetBasis;

        // 2^64, to ensure modular operations on 64 bits
        $modulo = gmp_init('18446744073709551616');

        // We iterate through each character in the string
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            // XOR between the hash and the ASCII code of the current character
            $hash = gmp_xor($hash, gmp_init(ord($data[$i])));
            // Multiply the hash by the prime constant
            $hash = gmp_mul($hash, $fnvPrime);
            // Perform modulo 2^64 to keep the result within 64 bits
            $hash = gmp_mod($hash, $modulo);
        }

        // Return the hash as a string
        return gmp_strval($hash);
    }

    /**
     * !! This method works only on 64-bit systems !!
     *
     * Computes the SHA-256 hash of a string and returns the first 64 bits as a decimal number
     * This method may be slower than the fnv1a64() but is more secure and less likely to produce collisions
     */
    function sha256To64Bit(string $data): string
    {
        // Obtain the complete hash as a hexadecimal string
        $fullHash = hash('sha256', $data);

        // Extract the first 16 characters (equivalent to 64 bits)
        $hash64Hex = substr($fullHash, 0, 16);

        // Convert from hexadecimal to decimal representation
        return base_convert($hash64Hex, 16, 10);
    }

    /**
     * !! This method works only on 64-bit systems !!
     *
     * Computes the SHA-256 hash of a string and returns the first 32 bits as a decimal number
     */
    function sha256To32Bit(string $data): string
    {
        // Obtain the complete hash as a hexadecimal string
        $fullHash = hash('sha256', $data);

        // Extract the first 8 characters (equivalent to 32 bits)
        $hash32Hex = substr($fullHash, 0, 8);

        // Convert from hexadecimal to decimal representation
        return base_convert($hash32Hex, 16, 10);
    }

    /**
     * !! This method works only on 64-bit systems !!
     *
     * Computes the SHA-256 hash of a string and returns the first 32 bits as a decimal number
     */
    function sha256To32BitUnsigned(string $data): string
    {
        return (string) ((int) $this->sha256To32Bit($data) % 2147483647);
    }
}
