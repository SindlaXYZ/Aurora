<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait BankTransferAmountTrait
{
    #[ORM\Column(name: 'bank_transfer_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Bank transfer amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Assert\GreaterThan(0, message: 'Bank transfer amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $bankTransferAmountWithoutVat = '0.00';

    #[ORM\Column(name: 'bank_transfer_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'Bank transfer VAT amount (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $bankTransferVatPercentage = '0.00';

    #[ORM\Column(name: 'bank_transfer_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'Bank transfer VAT Amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $bankTransferVatAmount = '0.00';

    #[ORM\Column(name: 'bank_transfer_amount_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Bank transfer amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount with VAT')]
    #[Assert\GreaterThan(0, message: 'Amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $bankTransferAmountWithVat = '0.00';

    public function getBankTransferAmountWithoutVat(): string
    {
        return $this->bankTransferAmountWithoutVat;
    }

    public function setBankTransferAmountWithoutVat(string $bankTransferAmountWithoutVat): self
    {
        $this->bankTransferAmountWithoutVat = $bankTransferAmountWithoutVat;
        return $this;
    }

    public function getCardVatPercentage(): string
    {
        return $this->bankTransferVatPercentage;
    }

    public function setCardVatPercentage(string $bankTransferVatPercentage): self
    {
        $this->bankTransferVatPercentage = $bankTransferVatPercentage;
        return $this;
    }

    public function getCardVatAmount(): string
    {
        return $this->bankTransferVatAmount;
    }

    public function setCardVatAmount(string $bankTransferVatAmount): self
    {
        $this->bankTransferVatAmount = $bankTransferVatAmount;
        return $this;
    }

    public function calculateBankTransferVatAmount(): self
    {
        $this->bankTransferVatAmount = bcdiv(bcmul($this->bankTransferAmountWithoutVat, bcdiv($this->bankTransferVatPercentage, 100, 2), 2), 1, 2);
        return $this;
    }

    public function getBankTransferAmountWithVat(): string
    {
        return $this->bankTransferAmountWithVat;
    }

    public function setBankTransferAmountWithVat(string $bankTransferAmountWithVat): self
    {
        $this->bankTransferAmountWithVat = $bankTransferAmountWithVat;
        return $this;
    }

    public function calculateBankTransferAmountWithVat(): self
    {
        $this->bankTransferAmountWithVat = bcadd($this->bankTransferAmountWithoutVat, $this->bankTransferVatAmount, 2);
        return $this;
    }
}
