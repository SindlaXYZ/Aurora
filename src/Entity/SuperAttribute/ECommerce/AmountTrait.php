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
        if ($this->amountWithoutVat) {
            $this->amountWithoutVat = bcsub($this->amountWithoutVat, $this->amountVatAmount, 2);
        } else if ($this->amountVatAmount) {
            $this->amountWithoutVat = bcdiv($this->amountVatAmount, bcadd(1, bcdiv($this->amountVatPercentage, 100, 2), 2), 2);
        }

        return $this;
    }

    public function calculateVatAmount(): self
    {
        $this->amountVatAmount = bcdiv(bcmul($this->amountWithoutVat, bcdiv($this->amountVatPercentage, 100, 2), 2), 1, 2);
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
