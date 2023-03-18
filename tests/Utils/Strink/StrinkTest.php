<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

    public function testFixDiacritics(): void
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

    public function testCamelCaseToSnakeCase(): void
    {
        $Strink = new Strink();
        foreach ([
                     'external_request_repository' => ['ExternalRequestRepository', 'externalRequestRepository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->camelCaseToSnakeCase());
            }
        }
    }
}
