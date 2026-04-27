<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PriceTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/ECommerce/PriceTraitTest.php --no-coverage
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

    public function testCalculatePriceVatAmount(): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithoutVat('123.45')
            ->setPriceVatPercentage('19');

        $expectedVatAmount = '23.45';

        $this->assertEquals(
            $expectedVatAmount,
            $priceTrait->calculatePriceVatAmount()->getPriceVatAmount(),
            "Price VAT amount should be {$expectedVatAmount} for price 123.45 with VAT percentage 19%"
        );
    }

}

class PriceTraitMock
{
    use PriceTrait;
}
