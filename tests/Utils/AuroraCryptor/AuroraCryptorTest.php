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

    public function testEncryptUsesUniqueInitializationVector(): void
    {
        $cryptor = new AuroraCryptor();
        $key    = 'myPa$$worD123';
        $data   = 'megaSecretKey';

        $first  = $cryptor->setEncryptionKey($key)->encrypt($data);
        $second = $cryptor->setEncryptionKey($key)->encrypt($data);

        $this->assertNotSame($first, $second);
    }
}
