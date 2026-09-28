<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\AmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer\BankTransferAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card\CardAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash\CashAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PricePerItemTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PriceTrait;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Entity/SuperAttribute/ECommerce/PriceTraitTest.php --no-coverage
 */
class PriceTraitTest extends TestCase
{
    public function testCalculatePriceVatAmount(): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithoutVat('123.45')
            ->setPriceVatPercentage('19');

        // 123.45 * 19% = 23.4555: rounded to the cent, it used to be truncated (23.45)
        $expectedVatAmount = '23.46';

        $this->assertEquals(
            $expectedVatAmount,
            $priceTrait->calculatePriceVatAmount()->getPriceVatAmount(),
            "Price VAT amount should be {$expectedVatAmount} for price 123.45 with VAT percentage 19%"
        );
    }

    public function testCalculatePriceVatAmountKeepsFractionalVatPercentage(): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithoutVat('100.00')
            ->setPriceVatPercentage('19.5');

        // The VAT percentage used to be truncated to 2 decimals after the division by 100 (19.5% => 0.19)
        $this->assertSame('19.50', $priceTrait->calculatePriceVatAmount()->getPriceVatAmount());
    }

    #[DataProvider('dataCalculatePriceWithoutVat')]
    public function testCalculatePriceWithoutVat(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat, string $expected): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithVat($withVat)
            ->setPriceVatAmount($vatAmount)
            ->setPriceVatPercentage($vatPercentage)
            ->setPriceWithoutVat($withoutVat);

        $this->assertSame($expected, $priceTrait->calculatePriceWithoutVat()->getPriceWithoutVat());
    }

    public static function dataCalculatePriceWithoutVat(): array
    {
        return [
            'with VAT - VAT amount'                => ['119.00', '19.00', '19.00', '0.00', '100.00'],
            'with VAT / (1 + VAT%)'                => ['119.00', '0.00', '19.00', '0.00', '100.00'],
            'with VAT / (1 + fractional VAT%)'     => ['105.50', '0.00', '5.50', '0.00', '100.00'],
            'with VAT and 0% VAT'                  => ['100.00', '0.00', '0.00', '0.00', '100.00'],
            'VAT amount / VAT%'                    => ['0.00', '19.00', '19.00', '0.00', '100.00'],
            'nothing to calculate from, unchanged' => ['0.00', '0.00', '19.00', '50.00', '50.00'],
        ];
    }

    #[DataProvider('dataCalculationsAreRoundedToTheCent')]
    public function testCalculationsAreRoundedToTheCent(string $withoutVat, string $withVat, string $vatPercentage, string $expectedVat, string $expectedWithoutVat): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithoutVat($withoutVat)
            ->setPriceVatPercentage($vatPercentage);

        // bcmath truncates: the VAT and the price without VAT were always rounded down, one cent less
        $this->assertSame($expectedVat, $priceTrait->calculatePriceVatAmount()->getPriceVatAmount());

        $priceTrait = new PriceTraitMock()
            ->setPriceWithVat($withVat)
            ->setPriceVatPercentage($vatPercentage);

        $this->assertSame($expectedWithoutVat, $priceTrait->calculatePriceWithoutVat()->getPriceWithoutVat());
    }

    public static function dataCalculationsAreRoundedToTheCent(): array
    {
        return [
            '10.99 at 19%' => ['10.99', '13.08', '19', '2.09', '10.99'],
            '17.00 at 19%' => ['14.29', '17.00', '19', '2.72', '14.29'],
            'half cent'    => ['0.50', '1.05', '1', '0.01', '1.04'],
            'below half'   => ['0.40', '0.40', '1', '0.00', '0.40'],
        ];
    }

    /**
     * The VAT amount of the previous calculation was subtracted from the new price with VAT: 119.00 used to be 109.00 without VAT
     *
     * @param \Closure(): string $recalculate Calculates 52.63 + 19% VAT, sets the price with VAT to 119.00 and returns the price without VAT
     */
    #[DataProvider('dataThePriceWithoutVatIsRecalculatedFromTheNewPriceWithVat')]
    public function testThePriceWithoutVatIsRecalculatedFromTheNewPriceWithVat(\Closure $recalculate): void
    {
        $this->assertSame('100.00', $recalculate());
    }

    public static function dataThePriceWithoutVatIsRecalculatedFromTheNewPriceWithVat(): iterable
    {
        yield 'price' => [static fn(): string => new PriceTraitMock()
            ->setPriceWithoutVat('52.63')->setPriceVatPercentage('19')->calculatePriceVatAmount()->calculatePriceWithVat()
            ->setPriceWithVat('119.00')->calculatePriceWithoutVat()->getPriceWithoutVat()];
        yield 'price per item' => [static fn(): string => new PricePerItemTraitMock()
            ->setPricePerItemWithoutVat('52.63')->setPricePerItemVatPercentage('19')->calculatePricePerItemVatAmount()->calculatePricePerItemWithVat()
            ->setPricePerItemWithVat('119.00')->calculatePricePerItemWithoutVat()->getPricePerItemWithoutVat()];
        yield 'amount' => [static fn(): string => new AmountTraitMock()
            ->setAmountWithoutVat('52.63')->setAmountVatPercentage('19')->calculateVatAmount()->calculateAmountWithVat()
            ->setAmountWithVat('119.00')->calculateAmountWithoutVat()->getAmountWithoutVat()];
        yield 'card' => [static fn(): string => new PaymentMethodAmountTraitsMock()
            ->setCardAmountWithoutVat('52.63')->setCardVatPercentage('19')->calculateCardVatAmount()->calculateCardAmountWithVat()
            ->setCardAmountWithVat('119.00')->calculateCardAmountWithoutVat()->getCardAmountWithoutVat()];
        yield 'cash' => [static fn(): string => new PaymentMethodAmountTraitsMock()
            ->setCashAmountWithoutVat('52.63')->setCashVatPercentage('19')->calculateCashVatAmount()->calculateCashAmountWithVat()
            ->setCashAmountWithVat('119.00')->calculateCashAmountWithoutVat()->getCashAmountWithoutVat()];
        yield 'bank transfer' => [static fn(): string => new PaymentMethodAmountTraitsMock()
            ->setBankTransferAmountWithoutVat('52.63')->setBankTransferVatPercentage('19')->calculateBankTransferVatAmount()->calculateBankTransferAmountWithVat()
            ->setBankTransferAmountWithVat('119.00')->calculateBankTransferAmountWithoutVat()->getBankTransferAmountWithoutVat()];
    }

    public function testCalculatePriceWithVat(): void
    {
        $priceTrait = new PriceTraitMock()
            ->setPriceWithoutVat('100.00')
            ->setPriceVatPercentage('19')
            ->calculatePriceVatAmount()
            ->calculatePriceWithVat();

        $this->assertSame('119.00', $priceTrait->getPriceWithVat());
    }
}

class PriceTraitMock
{
    use PriceTrait;
}

class PricePerItemTraitMock
{
    use PricePerItemTrait;
}

class AmountTraitMock
{
    use AmountTrait;
}

class PaymentMethodAmountTraitsMock
{
    use CardAmountTrait;
    use CashAmountTrait;
    use BankTransferAmountTrait;
}
