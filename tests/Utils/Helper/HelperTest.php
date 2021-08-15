<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\auroraBundle\Utils\Helper\Helper;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Helper/HelperTest.php --no-coverage
 */
class HelperTest extends KernelTestCase
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

    public function arrayToFlattenedDotPath()
    {
        $Helper = new Helper($this->containerTest);

        $nestedArray = [
            'this' => [
                [
                    'is'  => 'nested',
                    'and' => [
                        'more' => 'nested'
                    ]
                ],
                [
                    'lorem' => 'ipsum'
                ]
            ]
        ];

        $flattenedArray = [
            'this.0.is'       => 'nested',
            'this.0.and.more' => 'nested',
            'this.1.lorem'    => 'ipsum'
        ];

        $this->assertEquals($flattenedArray, $Helper->arrayToFlattenedDotPath($nestedArray));
    }
}
