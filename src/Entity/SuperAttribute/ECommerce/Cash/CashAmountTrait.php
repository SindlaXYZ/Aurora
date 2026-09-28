<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CashAmountTrait
{
    #[ORM\Column(name: 'cash_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Cash amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cashAmountWithoutVat = '0.00';

    #[ORM\Column(name: 'cash_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'Cash VAT amount (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cashVatPercentage = '0.00';

    #[ORM\Column(name: 'cash_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'Cash VAT Amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cashVatAmount = '0.00';

    #[ORM\Column(name: 'cash_amount_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Cash amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount with VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cashAmountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateCashAmountWithoutVat(): self
    {
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->cashAmountWithVat, '0', 2)) {
            // Without VAT = with VAT / (1 + VAT%), or with VAT - VAT amount when there is no VAT%: the VAT amount is the result of a previous
            // calculation, stale once the price with VAT changes (119.00 with the VAT 10.00 of 52.63 used to be 109.00 without VAT)
            $this->cashAmountWithoutVat = (0 !== bccomp($this->cashVatPercentage, '0', 2))
                ? bcround(bcdiv($this->cashAmountWithVat, bcadd('1', bcdiv($this->cashVatPercentage, '100', 6), 6), 10), 2)
                : bcsub($this->cashAmountWithVat, $this->cashVatAmount, 2);
        } else if (0 !== bccomp($this->cashVatAmount, '0', 2) && 0 !== bccomp($this->cashVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->cashAmountWithoutVat = bcround(bcdiv($this->cashVatAmount, bcdiv($this->cashVatPercentage, '100', 6), 10), 2);
        }

        return $this;
    }


    public function calculateCashVatAmount(): self
    {
        // Rounded to the cent (half away from zero): bcmath truncates, e.g. the VAT of 10.99 at 19% was 2.08 instead of 2.09
        $this->cashVatAmount = bcround(bcmul($this->cashAmountWithoutVat, bcdiv($this->cashVatPercentage, '100', 6), 10), 2);
        return $this;
    }

    public function calculateCashAmountWithVat(): self
    {
        $this->cashAmountWithVat = bcadd($this->cashAmountWithoutVat, $this->cashVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getCashAmountWithoutVat(): string
    {
        return $this->cashAmountWithoutVat;
    }

    public function setCashAmountWithoutVat(string $cashAmountWithoutVat): self
    {
        $this->cashAmountWithoutVat = $cashAmountWithoutVat;
        return $this;
    }

    public function getCashVatPercentage(): string
    {
        return $this->cashVatPercentage;
    }

    public function setCashVatPercentage(string $cashVatPercentage): self
    {
        $this->cashVatPercentage = $cashVatPercentage;
        return $this;
    }

    public function getCashVatAmount(): string
    {
        return $this->cashVatAmount;
    }

    public function setCashVatAmount(string $cashVatAmount): self
    {
        $this->cashVatAmount = $cashVatAmount;
        return $this;
    }

    public function getCashAmountWithVat(): string
    {
        return $this->cashAmountWithVat;
    }

    public function setCashAmountWithVat(string $cashAmountWithVat): self
    {
        $this->cashAmountWithVat = $cashAmountWithVat;
        return $this;
    }
}
