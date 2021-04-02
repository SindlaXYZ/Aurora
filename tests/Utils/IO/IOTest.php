<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\IO;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\IO\IO;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/IO/IOTest.php --no-coverage
 */
class IOTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testFileIsOlderThan()
    {
        /** @var  $IO */
        $IO = new IO();

        // Temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'aurora');

        $this->assertFalse($IO->fileIsOlderThan($tmpFile, 1, IO::TIME_UNIT_MINUTES));
        sleep(3);
        $this->assertTrue($IO->fileIsOlderThan($tmpFile, 1, IO::TIME_UNIT_SECONDS));
        unlink($tmpFile);
    }
}