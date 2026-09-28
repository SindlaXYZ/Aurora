<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\DiscountPerItemTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\DiscountTrait;

/**
 * DiscountTrait and DiscountPerItemTrait: the Card, Cash and BankTransfer discount traits are tested by PaymentMethodTraitsTest
 */
class DiscountTraitTest extends TestCase
{
    #[DataProvider('dataDiscounts')]
    public function testANewEntityHasNoDiscount(object $entity, string $discount): void
    {
        $this->assertNull($entity->{sprintf('get%sAmount', $discount)}());
        $this->assertNull($entity->{sprintf('get%sPercentage', $discount)}());
    }

    #[DataProvider('dataDiscounts')]
    public function testCalculateTheDiscountAmount(object $entity, string $discount): void
    {
        $this->assertSame($entity, $entity->{sprintf('set%sPercentage', $discount)}('10'));
        $this->assertSame($entity, $entity->{sprintf('set%sAmount', $discount)}('5.00'));

        // The stored percentage is used when none is given, the fixed amount is replaced by the calculated one
        $this->assertSame($entity, $entity->{sprintf('calculate%sAmount', $discount)}('200.00'));
        $this->assertSame('20.00', $entity->{sprintf('get%sAmount', $discount)}());

        // A given percentage takes precedence over the stored one, which is kept
        $entity->{sprintf('calculate%sAmount', $discount)}('200.00', '12.5');
        $this->assertSame('25.00', $entity->{sprintf('get%sAmount', $discount)}());
        $this->assertSame('10', $entity->{sprintf('get%sPercentage', $discount)}());

        // Rounded to the cent (half away from zero): 15% of 10.99 = 1.6485
        $entity->{sprintf('calculate%sAmount', $discount)}('10.99', '15');
        $this->assertSame('1.65', $entity->{sprintf('get%sAmount', $discount)}());

        $entity->{sprintf('set%sAmount', $discount)}(null);
        $entity->{sprintf('set%sPercentage', $discount)}(null);
        $this->assertNull($entity->{sprintf('get%sAmount', $discount)}());
        $this->assertNull($entity->{sprintf('get%sPercentage', $discount)}());
    }

    #[DataProvider('dataDiscounts')]
    public function testADiscountAmountCannotBeCalculatedWithoutAPercentage(object $entity, string $discount): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Discount percentage is required to calculate discount amount');

        $entity->{sprintf('calculate%sAmount', $discount)}('200.00');
    }

    public static function dataDiscounts(): iterable
    {
        yield 'discount' => [new class {
            use DiscountTrait;
        }, 'Discount'];
        yield 'discount per item' => [new class {
            use DiscountPerItemTrait;
        }, 'DiscountPerItem'];
    }
}
