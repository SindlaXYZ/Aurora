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
            'VAT amount without VAT%, unchanged'   => ['0.00', '19.00', '0.00', '50.00', '50.00'],
        ];
    }

    /**
     * @param \Closure(string, string, string, string): string $calculate Sets the amounts with VAT, VAT amount, VAT % and without VAT, returns the
     *                                                          calculated amount without VAT
     */
    #[DataProvider('dataCalculateAmountsWithoutVat')]
    public function testCalculateAmountsWithoutVat(\Closure $calculate, string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat, string $expected): void
    {
        $this->assertSame($expected, $calculate($withVat, $vatAmount, $vatPercentage, $withoutVat));
    }

    public static function dataCalculateAmountsWithoutVat(): iterable
    {
        $traits = [
            'price per item' => static fn(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat): string => new PricePerItemTraitMock()
                ->setPricePerItemWithVat($withVat)->setPricePerItemVatAmount($vatAmount)->setPricePerItemVatPercentage($vatPercentage)
                ->setPricePerItemWithoutVat($withoutVat)->calculatePricePerItemWithoutVat()->getPricePerItemWithoutVat(),
            'amount'         => static fn(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat): string => new AmountTraitMock()
                ->setAmountWithVat($withVat)->setAmountVatAmount($vatAmount)->setAmountVatPercentage($vatPercentage)
                ->setAmountWithoutVat($withoutVat)->calculateAmountWithoutVat()->getAmountWithoutVat(),
            'card'           => static fn(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat): string => new PaymentMethodAmountTraitsMock()
                ->setCardAmountWithVat($withVat)->setCardVatAmount($vatAmount)->setCardVatPercentage($vatPercentage)
                ->setCardAmountWithoutVat($withoutVat)->calculateCardAmountWithoutVat()->getCardAmountWithoutVat(),
            'cash'           => static fn(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat): string => new PaymentMethodAmountTraitsMock()
                ->setCashAmountWithVat($withVat)->setCashVatAmount($vatAmount)->setCashVatPercentage($vatPercentage)
                ->setCashAmountWithoutVat($withoutVat)->calculateCashAmountWithoutVat()->getCashAmountWithoutVat(),
            'bank transfer'  => static fn(string $withVat, string $vatAmount, string $vatPercentage, string $withoutVat): string => new PaymentMethodAmountTraitsMock()
                ->setBankTransferAmountWithVat($withVat)->setBankTransferVatAmount($vatAmount)->setBankTransferVatPercentage($vatPercentage)
                ->setBankTransferAmountWithoutVat($withoutVat)->calculateBankTransferAmountWithoutVat()->getBankTransferAmountWithoutVat(),
        ];

        // "with VAT / (1 + VAT%)" is asserted for every trait by testThePriceWithoutVatIsRecalculatedFromTheNewPriceWithVat()
        $scenarios = [
            'with VAT - VAT amount'                => ['119.00', '19.00', '0.00', '0.00', '100.00'],
            'VAT amount / VAT%'                    => ['0.00', '19.00', '19.00', '0.00', '100.00'],
            'nothing to calculate from, unchanged' => ['0.00', '0.00', '19.00', '50.00', '50.00'],
            'VAT amount without VAT%, unchanged'   => ['0.00', '19.00', '0.00', '50.00', '50.00'],
        ];

        foreach ($traits as $trait => $calculate) {
            foreach ($scenarios as $scenario => $amounts) {
                // Cash "with VAT - VAT amount" is asserted by PaymentMethodTraitsTest::testCardAndCashAmountsWithoutVat()
                if ('cash' === $trait && 'with VAT - VAT amount' === $scenario) {
                    continue;
                }

                yield sprintf('%s, %s', $trait, $scenario) => [$calculate, ...$amounts];
            }
        }
    }

    /**
     * @param \Closure(): array{string, string, string} $calculate Calculates the VAT and the amount with VAT of 100.00 without VAT at 19%, returns
     *                                                    the VAT %, the VAT amount and the amount with VAT
     */
    #[DataProvider('dataCalculateVatAndAmountWithVat')]
    public function testCalculateVatAndAmountWithVat(\Closure $calculate): void
    {
        $this->assertSame(['19', '19.00', '119.00'], $calculate());
    }

    public static function dataCalculateVatAndAmountWithVat(): iterable
    {
        yield 'price per item' => [static function (): array {
            $entity = new PricePerItemTraitMock()
                ->setPricePerItemWithoutVat('100.00')->setPricePerItemVatPercentage('19')->calculatePricePerItemVatAmount()->calculatePricePerItemWithVat();

            return [$entity->getPricePerItemVatPercentage(), $entity->getPricePerItemVatAmount(), $entity->getPricePerItemWithVat()];
        }];
        yield 'amount' => [static function (): array {
            $entity = new AmountTraitMock()->setAmountWithoutVat('100.00')->setAmountVatPercentage('19')->calculateVatAmount()->calculateAmountWithVat();

            return [$entity->getAmountVatPercentage(), $entity->getAmountVatAmount(), $entity->getAmountWithVat()];
        }];
        yield 'card' => [static function (): array {
            $entity = new PaymentMethodAmountTraitsMock()
                ->setCardAmountWithoutVat('100.00')->setCardVatPercentage('19')->calculateCardVatAmount()->calculateCardAmountWithVat();

            return [$entity->getCardVatPercentage(), $entity->getCardVatAmount(), $entity->getCardAmountWithVat()];
        }];
        yield 'cash' => [static function (): array {
            $entity = new PaymentMethodAmountTraitsMock()
                ->setCashAmountWithoutVat('100.00')->setCashVatPercentage('19')->calculateCashVatAmount()->calculateCashAmountWithVat();

            return [$entity->getCashVatPercentage(), $entity->getCashVatAmount(), $entity->getCashAmountWithVat()];
        }];
        yield 'bank transfer' => [static function (): array {
            $entity = new PaymentMethodAmountTraitsMock()
                ->setBankTransferAmountWithoutVat('100.00')->setBankTransferVatPercentage('19')->calculateBankTransferVatAmount()->calculateBankTransferAmountWithVat();

            return [$entity->getBankTransferVatPercentage(), $entity->getBankTransferVatAmount(), $entity->getBankTransferAmountWithVat()];
        }];
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
        $this->assertSame('19', $priceTrait->getPriceVatPercentage());
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
