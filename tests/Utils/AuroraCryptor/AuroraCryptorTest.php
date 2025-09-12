<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCryptor;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCryptor\AuroraCryptor;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraCryptor/AuroraCryptorTest.php --no-coverage
 */
class AuroraCryptorTest extends TestCase
{
    public function testEncryptAndDecrypt(): void
    {
        $cryptor = new AuroraCryptor();
        $key = 'myPa$$worD123';
        $data = 'megaSecretKey';

        $encrypted = $cryptor->setEncryptionKey($key)->encrypt($data);
        $decoded = base64_decode($encrypted, true);

        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('::', $decoded);

        $decrypted = $cryptor->setEncryptionKey($key)->decrypt($encrypted);
        $this->assertSame($data, $decrypted);
    }

    public function testSetCipherRegeneratesInitializationVector(): void
    {
        $cipher  = 'DES-EDE3-CBC';
        $key     = '0123456789abcdef01234567';
        $cryptor = new AuroraCryptor();

        $encrypted = $cryptor->setCipher($cipher)
            ->setEncryptionKey($key)
            ->encrypt('top');

        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);

        [, $vector] = explode('::', $decoded, 2);

        $this->assertSame(openssl_cipher_iv_length($cipher), strlen($vector));
        $decrypted = $cryptor->setEncryptionKey($key)->decrypt($encrypted);
        $this->assertSame('top', $decrypted);
    }
}
