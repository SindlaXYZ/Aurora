<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CardAmountTrait
{
    #[ORM\Column(name: 'card_amount_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Card amount with VAT'])]
    #[FormElement(searchable: true, label: 'Amount without VAT')]
    #[Assert\GreaterThan(0, message: 'Card amount with VAT must be greater than 0.')]
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
    #[Assert\GreaterThan(0, message: 'Amount with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $cardAmountWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculateCardAmountWithoutVat(): self
    {
        if ($this->cardAmountWithoutVat) {
            $this->cardAmountWithoutVat = bcsub($this->cardAmountWithoutVat, $this->cardVatAmount, 2);
        } else if ($this->cardVatAmount) {
            $this->cardAmountWithoutVat = bcdiv($this->cardVatAmount, bcadd(1, bcdiv($this->cardVatPercentage, 100, 2), 2), 2);
        }

        return $this;
    }

    public function calculateCardVatAmount(): self
    {
        $this->cardVatAmount = bcdiv(bcmul($this->cardAmountWithoutVat, bcdiv($this->cardVatPercentage, 100, 2), 2), 1, 2);
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
