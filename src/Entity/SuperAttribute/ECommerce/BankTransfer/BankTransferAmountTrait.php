<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait BankTransferAmountTrait
{
    #[ORM\Column(name: 'bank_transfer_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Bank transfer amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
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
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $bankTransferAmountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateBankTransferAmountWithoutVat(): self
    {
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->bankTransferAmountWithVat, '0', 2)) {
            // Without VAT = with VAT - VAT amount, or with VAT / (1 + VAT%) when the VAT amount is not known
            $this->bankTransferAmountWithoutVat = (0 !== bccomp($this->bankTransferVatAmount, '0', 2))
                ? bcsub($this->bankTransferAmountWithVat, $this->bankTransferVatAmount, 2)
                : bcdiv($this->bankTransferAmountWithVat, bcadd('1', bcdiv($this->bankTransferVatPercentage, '100', 6), 6), 2);
        } else if (0 !== bccomp($this->bankTransferVatAmount, '0', 2) && 0 !== bccomp($this->bankTransferVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->bankTransferAmountWithoutVat = bcdiv($this->bankTransferVatAmount, bcdiv($this->bankTransferVatPercentage, '100', 6), 2);
        }

        return $this;
    }

    public function calculateBankTransferVatAmount(): self
    {
        $this->bankTransferVatAmount = bcdiv(bcmul($this->bankTransferAmountWithoutVat, bcdiv($this->bankTransferVatPercentage, 100, 6), 2), 1, 2);
        return $this;
    }

    public function calculateBankTransferAmountWithVat(): self
    {
        $this->bankTransferAmountWithVat = bcadd($this->bankTransferAmountWithoutVat, $this->bankTransferVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getBankTransferAmountWithoutVat(): string
    {
        return $this->bankTransferAmountWithoutVat;
    }

    public function setBankTransferAmountWithoutVat(string $bankTransferAmountWithoutVat): self
    {
        $this->bankTransferAmountWithoutVat = $bankTransferAmountWithoutVat;
        return $this;
    }

    public function getBankTransferVatPercentage(): string
    {
        return $this->bankTransferVatPercentage;
    }

    public function setBankTransferVatPercentage(string $bankTransferVatPercentage): self
    {
        $this->bankTransferVatPercentage = $bankTransferVatPercentage;
        return $this;
    }

    public function getBankTransferVatAmount(): string
    {
        return $this->bankTransferVatAmount;
    }

    public function setBankTransferVatAmount(string $bankTransferVatAmount): self
    {
        $this->bankTransferVatAmount = $bankTransferVatAmount;
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
}
