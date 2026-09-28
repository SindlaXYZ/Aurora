<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer\BankTransferAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer\BankTransferDiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card\CardAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card\CardDiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash\CashAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash\CashDiscountTrait;

/**
 * The Card, Cash and BankTransfer traits must be usable together: the BankTransfer traits used to declare copy-pasted
 * Card/Cash method names (getCardVatPercentage(), calculateCashDiscountAmount(), ...), a fatal "trait method collision".
 */
class PaymentMethodTraitsTest extends TestCase
{
    public function testBankTransferVatAccessorsDoNotOverrideCardAccessors(): void
    {
        $entity = new PaymentMethodTraitsMock()
            ->setCardVatPercentage('21.00')
            ->setCardVatAmount('2.10')
            ->setBankTransferVatPercentage('19.00')
            ->setBankTransferVatAmount('1.90');

        $this->assertSame('21.00', $entity->getCardVatPercentage());
        $this->assertSame('2.10', $entity->getCardVatAmount());
        $this->assertSame('19.00', $entity->getBankTransferVatPercentage());
        $this->assertSame('1.90', $entity->getBankTransferVatAmount());
    }

    public function testBankTransferAmountCalculations(): void
    {
        $entity = new PaymentMethodTraitsMock()
            ->setBankTransferAmountWithVat('119.00')
            ->setBankTransferVatPercentage('19.00')
            ->calculateBankTransferAmountWithoutVat()
            ->calculateBankTransferVatAmount();

        $this->assertSame('100.00', $entity->getBankTransferAmountWithoutVat());
        $this->assertSame('19.00', $entity->getBankTransferVatAmount());
    }

    public function testCardAndCashAmountsWithoutVat(): void
    {
        $entity = new PaymentMethodTraitsMock()
            ->setCardAmountWithVat('121.00')
            ->setCardVatPercentage('21.00')
            ->calculateCardAmountWithoutVat()
            ->setCashAmountWithVat('119.00')
            ->setCashVatAmount('19.00')
            ->calculateCashAmountWithoutVat();

        $this->assertSame('100.00', $entity->getCardAmountWithoutVat());
        $this->assertSame('100.00', $entity->getCashAmountWithoutVat());
    }

    public function testDiscountAmountsAreCalculatedPerPaymentMethod(): void
    {
        $entity = new PaymentMethodTraitsMock()
            ->calculateCardDiscountAmount('200.00', '5')
            ->calculateCashDiscountAmount('200.00', '10')
            ->calculateBankTransferDiscountAmount('200.00', '15');

        $this->assertSame('10.00', $entity->getCardDiscountAmount());
        $this->assertSame('20.00', $entity->getCashDiscountAmount());
        $this->assertSame('30.00', $entity->getBankTransferDiscountAmount());
    }

    public function testAmountsAreRoundedToTheCent(): void
    {
        $entity = new PaymentMethodTraitsMock()
            ->calculateCardDiscountAmount('10.99', '15')
            ->calculateCashDiscountAmount('10.99', '15')
            ->calculateBankTransferDiscountAmount('10.99', '15')
            ->setCardAmountWithoutVat('10.99')
            ->setCardVatPercentage('19')
            ->calculateCardVatAmount()
            ->setCashAmountWithVat('17.00')
            ->setCashVatPercentage('19')
            ->calculateCashAmountWithoutVat();

        // bcmath truncates: 15% of 10.99 (1.6485) was 1.64, the VAT of 10.99 at 19% (2.0881) was 2.08, 17.00 / 1.19 (14.2857) was 14.28
        $this->assertSame('1.65', $entity->getCardDiscountAmount());
        $this->assertSame('1.65', $entity->getCashDiscountAmount());
        $this->assertSame('1.65', $entity->getBankTransferDiscountAmount());
        $this->assertSame('2.09', $entity->getCardVatAmount());
        $this->assertSame('14.29', $entity->getCashAmountWithoutVat());
    }

    #[DataProvider('dataDiscounts')]
    public function testTheStoredDiscountPercentageIsUsedWhenNoneIsGiven(string $discount): void
    {
        $entity = new PaymentMethodTraitsMock();

        $this->assertSame($entity, $entity->{sprintf('set%sPercentage', $discount)}('10'));
        $this->assertSame($entity, $entity->{sprintf('set%sAmount', $discount)}('5.00'));
        $entity->{sprintf('calculate%sAmount', $discount)}('200.00');

        // The fixed amount is replaced by the calculated one
        $this->assertSame('10', $entity->{sprintf('get%sPercentage', $discount)}());
        $this->assertSame('20.00', $entity->{sprintf('get%sAmount', $discount)}());

        // A given percentage takes precedence over the stored one, which is kept
        $entity->{sprintf('calculate%sAmount', $discount)}('200.00', '25');
        $this->assertSame('50.00', $entity->{sprintf('get%sAmount', $discount)}());
        $this->assertSame('10', $entity->{sprintf('get%sPercentage', $discount)}());

        $entity->{sprintf('set%sAmount', $discount)}(null);
        $entity->{sprintf('set%sPercentage', $discount)}(null);
        $this->assertNull($entity->{sprintf('get%sAmount', $discount)}());
        $this->assertNull($entity->{sprintf('get%sPercentage', $discount)}());
    }

    #[DataProvider('dataDiscountsWithoutAPercentage')]
    public function testADiscountAmountCannotBeCalculatedWithoutAPercentage(string $discount, string $message): void
    {
        // The other payment methods have a percentage: each one must check its own
        $entity = new PaymentMethodTraitsMock()->setCardDiscountPercentage('5')->setCashDiscountPercentage('10')->setBankTransferDiscountPercentage('15');
        $entity->{sprintf('set%sPercentage', $discount)}(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        $entity->{sprintf('calculate%sAmount', $discount)}('200.00');
    }

    public static function dataDiscounts(): iterable
    {
        yield 'card' => ['CardDiscount'];
        yield 'cash' => ['CashDiscount'];
        yield 'bank transfer' => ['BankTransferDiscount'];
    }

    public static function dataDiscountsWithoutAPercentage(): iterable
    {
        yield 'card' => ['CardDiscount', 'Discount percentage is required to calculate discount amount'];
        yield 'cash' => ['CashDiscount', 'Discount percentage is required to calculate discount amount'];
        yield 'bank transfer' => ['BankTransferDiscount', 'Bank transfer discount percentage is required to calculate discount amount'];
    }
}

class PaymentMethodTraitsMock
{
    use CardAmountTrait;
    use CardDiscountTrait;
    use CashAmountTrait;
    use CashDiscountTrait;
    use BankTransferAmountTrait;
    use BankTransferDiscountTrait;
}
