<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCryptor;

use InvalidArgumentException;
use RuntimeException;
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

    public function testEncryptUsesUniqueInitializationVector(): void
    {
        $cryptor = new AuroraCryptor();
        $key    = 'myPa$$worD123';
        $data   = 'megaSecretKey';

        $first  = $cryptor->setEncryptionKey($key)->encrypt($data);
        $second = $cryptor->setEncryptionKey($key)->encrypt($data);

        $this->assertNotSame($first, $second);
    }

    public function testDecryptSupportsInitializationVectorsContainingDelimiter(): void
    {
        $cryptor = new AuroraCryptor();
        $key     = 'myPa$$worD123';
        $vector  = str_repeat(':', openssl_cipher_iv_length('AES-128-CTR'));
        $data    = 'megaSecretKey';

        $encrypted = openssl_encrypt($data, 'AES-128-CTR', $key, 0, $vector);
        $payload   = base64_encode($encrypted . '::' . $vector);

        $decrypted = $cryptor->setEncryptionKey($key)->decrypt($payload);

        $this->assertSame($data, $decrypted);
    }

    public function testDecryptRejectsInvalidBase64Payload(): void
    {
        $cryptor = new AuroraCryptor();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encrypted payload must be valid base64.');

        $cryptor->setEncryptionKey('test')->decrypt('not-base64');
    }

    public function testDecryptThrowsWhenDecryptionFails(): void
    {
        $cryptor = new AuroraCryptor();
        $cryptor->setCipher('AES-128-CBC');
        $key     = '0123456789abcdef';
        $payload = $cryptor->setEncryptionKey($key)->encrypt('secret-data');

        $decoded = base64_decode($payload, true);
        $this->assertNotFalse($decoded);

        [$data, $vector] = explode('::', $decoded, 2);
        $tamperedPayload = base64_encode(substr($data, 0, 2) . '::' . $vector);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to decrypt the provided data.');

        $cryptor->setEncryptionKey($key)->decrypt($tamperedPayload);
    }

    public function testSha256To32BitUnsigned(): void
    {
        $cryptor = new AuroraCryptor();
        $result  = $cryptor->sha256To32BitUnsigned('example');

        $this->assertIsString($result);
        $value = (int) $result;
        $this->assertGreaterThanOrEqual(0, $value);
        $this->assertLessThanOrEqual(2147483647, $value);
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
