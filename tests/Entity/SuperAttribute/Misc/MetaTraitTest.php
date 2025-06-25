<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Misc;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc\MetaTrait;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/Misc/MetaTraitTest.php --no-coverage
 */
class MetaTraitTest extends KernelTestCase
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

//    public function testCalculatePriceVatAmount(): void
//    {
//        /** @var PriceTrait $priceTrait */
//        $priceTrait = $this->getMockForTrait('Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PriceTrait');
//
//        $priceTrait
//            ->setPriceWithoutVat('123.45')
//            ->setPriceVatPercentage('19.5');
//
//        $this->assertEquals(
//            '24.07',
//            $priceTrait->calculatePriceVatAmount(),
//            'Price VAT amount should be 24.07 for price 123.45 with VAT percentage 19.50%'
//        );
//    }
}
