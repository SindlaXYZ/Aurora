<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CashAmountTrait
{
    #[ORM\Column(name: 'cash_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Cash amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Assert\GreaterThan(0, message: 'Cash amount with VAT must be greater than 0.')]
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
    #[Assert\GreaterThan(0, message: 'Amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cashAmountWithVat = '0.00';

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

    public function calculateCashVatAmount(): self
    {
        $this->cashVatAmount = bcdiv(bcmul($this->cashAmountWithoutVat, bcdiv($this->cashVatPercentage, 100, 2), 2), 1, 2);
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

    public function calculateCashAmountWithVat(): self
    {
        $this->cashAmountWithVat = bcadd($this->cashAmountWithoutVat, $this->cashVatAmount, 2);
        return $this;
    }
}
