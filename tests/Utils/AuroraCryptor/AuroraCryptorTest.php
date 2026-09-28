<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCryptor;

use InvalidArgumentException;
use RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCryptor\AuroraCryptor;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraCryptor/AuroraCryptorTest.php --no-coverage
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

    #[DataProvider('dataSha256To32BitUnsigned')]
    public function testSha256To32BitUnsigned(string $input, string $expected, bool $exceedsSignedLimit): void
    {
        $cryptor = new AuroraCryptor();
        $result  = $cryptor->sha256To32BitUnsigned($input);

        $this->assertSame($expected, $result);
        $this->assertSame($expected, $cryptor->sha256To32Bit($input));
        $this->assertTrue(ctype_digit($result));

        $numericResult = (float) $result;
        $this->assertGreaterThanOrEqual(0.0, $numericResult);
        $this->assertLessThanOrEqual(4294967295.0, $numericResult);

        if ($exceedsSignedLimit) {
            $this->assertGreaterThan(2147483647.0, $numericResult);
        } else {
            $this->assertLessThanOrEqual(2147483647.0, $numericResult);
        }
    }

    public static function dataSha256To32BitUnsigned(): array
    {
        return [
            ['example', '1356355808', false],
            ['test', '2676412545', true],
        ];
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

    public function testSetCipherRejectsACipherWithoutInitializationVector(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cipher "AES-128-ECB" requires a positive initialization vector length.');

        new AuroraCryptor()->setCipher('AES-128-ECB');
    }

    public function testSetCipherRejectsAnUnknownCipher(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cipher "not-a-cipher" is not supported.');

        // openssl_cipher_iv_length() also emits an "Unknown cipher algorithm" warning
        @new AuroraCryptor()->setCipher('not-a-cipher');
    }

    #[DataProvider('dataInvalidCipher')]
    public function testEncryptNeverUsesAnInvalidCipher(string $cipher, string $message): void
    {
        $cryptor = new AuroraCryptor();
        new \ReflectionProperty(AuroraCryptor::class, 'cipher')->setValue($cryptor, $cipher);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        @$cryptor->setEncryptionKey('your-key-here')->encrypt('secret-data');
    }

    public static function dataInvalidCipher(): array
    {
        return [
            'without initialization vector' => ['AES-128-ECB', 'Cipher "AES-128-ECB" requires a positive initialization vector length.'],
            'unknown'                       => ['not-a-cipher', 'Cipher "not-a-cipher" is not supported.'],
        ];
    }

    public function testEncryptRequiresAKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key must be provided before encrypting data.');

        new AuroraCryptor()->encrypt('secret-data');
    }

    public function testDecryptRequiresAKey(): void
    {
        $payload = new AuroraCryptor()->setEncryptionKey('your-key-here')->encrypt('secret-data');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key must be provided before decrypting data.');

        new AuroraCryptor()->decrypt($payload);
    }

    public function testTheKeyIsForgottenAfterEachOperation(): void
    {
        $cryptor = new AuroraCryptor();
        $cryptor->setEncryptionKey('your-key-here')->encrypt('secret-data');

        try {
            $cryptor->encrypt('secret-data');
            $this->fail('The key of the previous encryption is expected to be forgotten.');
        } catch (RuntimeException $e) {
            $this->assertSame('Encryption key must be provided before encrypting data.', $e->getMessage());
        }

        $payload = $cryptor->setEncryptionKey('your-key-here')->encrypt('secret-data');
        $this->assertSame('secret-data', $cryptor->setEncryptionKey('your-key-here')->decrypt($payload));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key must be provided before decrypting data.');

        $cryptor->decrypt($payload);
    }

    public function testDecryptRejectsAPayloadWithoutInitializationVector(): void
    {
        $cryptor = new AuroraCryptor()->setEncryptionKey('your-key-here');

        try {
            $cryptor->decrypt(base64_encode('encrypted-data-without-delimiter'));
            $this->fail('A payload without initialization vector is expected to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Encrypted payload is missing the initialization vector.', $e->getMessage());
        }

        // The key is forgotten after a rejected payload too
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key must be provided before decrypting data.');

        $cryptor->decrypt(new AuroraCryptor()->setEncryptionKey('your-key-here')->encrypt('secret-data'));
    }

    #[DataProvider('dataFnv1a64')]
    public function testFnv1a64(string $expected, string $input): void
    {
        $result = new AuroraCryptor()->fnv1a64($input);

        $this->assertSame($expected, $result);
        // The same hash as the native implementation
        $this->assertSame(gmp_strval(gmp_init(hash('fnv1a64', $input), 16)), $result);
    }

    public static function dataFnv1a64(): array
    {
        return [
            // Reference vectors: the offset basis for an empty string, 0xaf63dc4c8601ec8c, 0x85944171f73967e8
            'empty'     => ['14695981039346656037', ''],
            'a'         => ['12638187200555641996', 'a'],
            'foobar'    => ['9625390261332436968', 'foobar'],
            // The bytes of the UTF-8 string: 0xfc46ec772f254e99
            'multibyte' => ['18178476942563823257', 'București'],
        ];
    }

    /**
     * The first 64 bits of these hashes are below 2^63 (base_convert() is exact below it)
     */
    #[DataProvider('dataSha256To64Bit')]
    public function testSha256To64Bit(string $expected, string $input): void
    {
        $result = new AuroraCryptor()->sha256To64Bit($input);

        $this->assertSame($expected, $result);
        $this->assertSame(gmp_strval(gmp_init(substr(hash('sha256', $input), 0, 16), 16)), $result);
    }

    public static function dataSha256To64Bit(): array
    {
        return [
            // sha256("example") = 50d858e0985ecc7f...
            ['5825503839656004735', 'example'],
            // sha256("hello") = 2cf24dba5fb0a30e...
            ['3238736544897475342', 'hello'],
        ];
    }
}
