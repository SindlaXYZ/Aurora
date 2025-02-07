<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait AmountTrait
{
    #[ORM\Column(name: 'amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Assert\GreaterThan(0, message: 'Amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountWithoutVat = '0.00';

    #[ORM\Column(name: 'vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'VAT amount (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $vatPercentage = '0.00';

    #[ORM\Column(name: 'vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'VAT Amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $vatAmount = '0.00';

    #[ORM\Column(name: 'amount_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount with VAT')]
    #[Assert\GreaterThan(0, message: 'Amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $amountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateAmountWithoutVat(): self
    {
        if ($this->amountWithoutVat) {
            $this->amountWithoutVat = bcsub($this->amountWithoutVat, $this->vatAmount, 2);
        } else if ($this->vatAmount) {
            $this->amountWithoutVat = bcdiv($this->vatAmount, bcadd(1, bcdiv($this->vatPercentage, 100, 2), 2), 2);
        }

        return $this;
    }

    public function calculateVatAmount(): self
    {
        $this->vatAmount = bcdiv(bcmul($this->amountWithoutVat, bcdiv($this->vatPercentage, 100, 2), 2), 1, 2);
        return $this;
    }

    public function calculateAmountWithVat(): self
    {
        $this->amountWithVat = bcadd($this->amountWithoutVat, $this->vatAmount, 2);
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

    public function getVatPercentage(): string
    {
        return $this->vatPercentage;
    }

    public function setVatPercentage(string $vatPercentage): self
    {
        $this->vatPercentage = $vatPercentage;
        return $this;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    public function setVatAmount(string $vatAmount): self
    {
        $this->vatAmount = $vatAmount;
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
