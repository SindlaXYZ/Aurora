<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PriceTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/ECommerce/PriceTraitTest.php --no-coverage
 */
class PriceTraitTest extends KernelTestCase
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

    public function testCalculatePriceVatAmount(): void
    {
        $priceTrait = $this->createMock(PriceTraitMock::class)
            ->method('getPriceVatPercentage')->willReturn('19')
            ->method('getPriceWithoutVat')->willReturn('123.45');

        $priceTrait
            ->setPriceWithoutVat('123.45')
            ->setPriceVatPercentage('19');

        $this->assertEquals(
            '23.45',
            $priceTrait->calculatePriceVatAmount()->getPriceVatAmount(),
            'Price VAT amount should be 24.07 for price 123.45 with VAT percentage 19.50%'
        );
    }
}

class PriceTraitMock
{
    use PriceTrait;
}
