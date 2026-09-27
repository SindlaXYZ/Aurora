<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\ECommerce;

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
