<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Strink/StrinkTest.php --no-coverage
 */
class StrinkTest extends KernelTestCase
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

    public function testFixDiacritics()
    {
        $Strink = new Strink();

        foreach ([
                     'București'           => ['Bucureºti', 'Bucureşti'],
                     'Dumbrăvii'           => ['Dumbrãvii'],
                     'P-ța Rhedey Claudia' => ['P-þa Rhedey Claudia']
                 ] as $expected => $toFix) {
            foreach ($toFix as $actual) {
                $this->assertEquals($expected, $Strink->string($actual)->fixDiacritics('ro'));
            }
        }
    }
}