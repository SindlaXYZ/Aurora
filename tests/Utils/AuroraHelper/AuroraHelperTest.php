<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Sindla\Bundle\auroraBundle\Utils\AuroraHelper\AuroraHelper;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraHelper/AuroraHelperTest.php --no-coverage
 */
class AuroraHelperTest extends KernelTestCase
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

    public function arrayToFlattenedDotPath(): void
    {
        $Helper = new AuroraHelper();

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
