<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CardAmountTrait
{
    #[ORM\Column(name: 'card_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Card amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cardAmountWithoutVat = '0.00';

    #[ORM\Column(name: 'card_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'Card VAT amount (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cardVatPercentage = '0.00';

    #[ORM\Column(name: 'card_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'Card VAT Amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cardVatAmount = '0.00';

    #[ORM\Column(name: 'card_amount_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Card amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount with VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cardAmountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateCardAmountWithoutVat(): self
    {
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->cardAmountWithVat, '0', 2)) {
            // Without VAT = with VAT - VAT amount, or with VAT / (1 + VAT%) when the VAT amount is not known
            $this->cardAmountWithoutVat = (0 !== bccomp($this->cardVatAmount, '0', 2))
                ? bcsub($this->cardAmountWithVat, $this->cardVatAmount, 2)
                : bcdiv($this->cardAmountWithVat, bcadd('1', bcdiv($this->cardVatPercentage, '100', 6), 6), 2);
        } else if (0 !== bccomp($this->cardVatAmount, '0', 2) && 0 !== bccomp($this->cardVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->cardAmountWithoutVat = bcdiv($this->cardVatAmount, bcdiv($this->cardVatPercentage, '100', 6), 2);
        }

        return $this;
    }

    public function calculateCardVatAmount(): self
    {
        $this->cardVatAmount = bcdiv(bcmul($this->cardAmountWithoutVat, bcdiv($this->cardVatPercentage, 100, 6), 2), 1, 2);
        return $this;
    }

    public function calculateCardAmountWithVat(): self
    {
        $this->cardAmountWithVat = bcadd($this->cardAmountWithoutVat, $this->cardVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------


    public function getCardAmountWithoutVat(): string
    {
        return $this->cardAmountWithoutVat;
    }

    public function setCardAmountWithoutVat(string $cardAmountWithoutVat): self
    {
        $this->cardAmountWithoutVat = $cardAmountWithoutVat;
        return $this;
    }

    public function getCardVatPercentage(): string
    {
        return $this->cardVatPercentage;
    }

    public function setCardVatPercentage(string $cardVatPercentage): self
    {
        $this->cardVatPercentage = $cardVatPercentage;
        return $this;
    }

    public function getCardVatAmount(): string
    {
        return $this->cardVatAmount;
    }

    public function setCardVatAmount(string $cardVatAmount): self
    {
        $this->cardVatAmount = $cardVatAmount;
        return $this;
    }

    public function getCardAmountWithVat(): string
    {
        return $this->cardAmountWithVat;
    }

    public function setCardAmountWithVat(string $cardAmountWithVat): self
    {
        $this->cardAmountWithVat = $cardAmountWithVat;
        return $this;
    }
}
