<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait AmountTrait
{
    #[ORM\Column(name: 'amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountWithoutVat = '0.00';

    #[ORM\Column(name: 'amount_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'Amount VAT (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountVatPercentage = '0.00';

    #[ORM\Column(name: 'amount_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'Amount VAT amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountVatAmount = '0.00';

    #[ORM\Column(name: 'amount_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount with VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateAmountWithoutVat(): self
    {
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->amountWithVat, '0', 2)) {
            // Without VAT = with VAT / (1 + VAT%), or with VAT - VAT amount when there is no VAT%: the VAT amount is the result of a previous
            // calculation, stale once the price with VAT changes (119.00 with the VAT 10.00 of 52.63 used to be 109.00 without VAT)
            $this->amountWithoutVat = (0 !== bccomp($this->amountVatPercentage, '0', 2))
                ? bcround(bcdiv($this->amountWithVat, bcadd('1', bcdiv($this->amountVatPercentage, '100', 6), 6), 10), 2)
                : bcsub($this->amountWithVat, $this->amountVatAmount, 2);
        } else if (0 !== bccomp($this->amountVatAmount, '0', 2) && 0 !== bccomp($this->amountVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->amountWithoutVat = bcround(bcdiv($this->amountVatAmount, bcdiv($this->amountVatPercentage, '100', 6), 10), 2);
        }

        return $this;
    }

    public function calculateVatAmount(): self
    {
        // Rounded to the cent (half away from zero): bcmath truncates, e.g. the VAT of 10.99 at 19% was 2.08 instead of 2.09
        $this->amountVatAmount = bcround(bcmul($this->amountWithoutVat, bcdiv($this->amountVatPercentage, '100', 6), 10), 2);
        return $this;
    }

    public function calculateAmountWithVat(): self
    {
        $this->amountWithVat = bcadd($this->amountWithoutVat, $this->amountVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getAmountWithoutVat(): string
    {
        return $this->amountWithoutVat;
    }

    public function setAmountWithoutVat(string $amountWithoutVat): self
    {
        $this->amountWithoutVat = $amountWithoutVat;
        return $this;
    }

    public function getAmountVatPercentage(): string
    {
        return $this->amountVatPercentage;
    }

    public function setAmountVatPercentage(string $amountVatPercentage): self
    {
        $this->amountVatPercentage = $amountVatPercentage;
        return $this;
    }

    public function getAmountVatAmount(): string
    {
        return $this->amountVatAmount;
    }

    public function setAmountVatAmount(string $amountVatAmount): self
    {
        $this->amountVatAmount = $amountVatAmount;
        return $this;
    }

    public function getAmountWithVat(): string
    {
        return $this->amountWithVat;
    }

    public function setAmountWithVat(string $amountWithVat): self
    {
        $this->amountWithVat = $amountWithVat;
        return $this;
    }
}
