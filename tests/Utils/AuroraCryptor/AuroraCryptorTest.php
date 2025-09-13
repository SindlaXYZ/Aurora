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

    public function testSha256To32BitUnsigned(): void
    {
        $cryptor = new AuroraCryptor();
        $result  = $cryptor->sha256To32BitUnsigned('example');

        $this->assertIsString($result);
        $value = (int) $result;
        $this->assertGreaterThanOrEqual(0, $value);
        $this->assertLessThanOrEqual(2147483647, $value);
    }
}
