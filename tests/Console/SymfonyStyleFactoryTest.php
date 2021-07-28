<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventListener;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

// Aurora
use Sindla\Bundle\AuroraBundle\Console\SymfonyStyleFactory;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Console/SymfonyStyleFactoryTest.php --no-coverage
 */
class SymfonyStyleFactoryTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testSymfonyStyle(): void
    {
        $symfonyStyleFactory = new SymfonyStyleFactory();
        $symfonyStyle        = $symfonyStyleFactory->create();

        $this->assertTrue($symfonyStyle instanceof SymfonyStyle);
    }
}
