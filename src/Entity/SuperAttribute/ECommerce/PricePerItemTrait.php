<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait PricePerItemTrait
{
    #[ORM\Column(name: 'price_per_item_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price per item without VAT'])]
    #[FormElement(searchable: true, label: 'Price per item without VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $pricePerItemWithoutVat = '0.00';

    #[ORM\Column(name: 'price_per_item_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'VAT amount per item (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage) per item')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $pricePerItemVatPercentage = '0.00';

    #[ORM\Column(name: 'price_per_item_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'VAT amount per item'])]
    #[FormElement(searchable: true, label: 'VAT amount per item')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $pricePerItemVatAmount = '0.00';

    #[ORM\Column(name: 'price_per_item_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price per item with VAT'])]
    #[FormElement(searchable: true, label: 'Price per item with VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $pricePerItemWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculatePricePerItemWithoutVat(): self
    {
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->pricePerItemWithVat, '0', 2)) {
            // Without VAT = with VAT - VAT amount, or with VAT / (1 + VAT%) when the VAT amount is not known
            $this->pricePerItemWithoutVat = (0 !== bccomp($this->pricePerItemVatAmount, '0', 2))
                ? bcsub($this->pricePerItemWithVat, $this->pricePerItemVatAmount, 2)
                : bcdiv($this->pricePerItemWithVat, bcadd('1', bcdiv($this->pricePerItemVatPercentage, '100', 6), 6), 2);
        } else if (0 !== bccomp($this->pricePerItemVatAmount, '0', 2) && 0 !== bccomp($this->pricePerItemVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->pricePerItemWithoutVat = bcdiv($this->pricePerItemVatAmount, bcdiv($this->pricePerItemVatPercentage, '100', 6), 2);
        }

        return $this;
    }

    public function calculatePricePerItemVatAmount(): self
    {
        $this->pricePerItemVatAmount = bcdiv(bcmul($this->pricePerItemWithoutVat, bcdiv($this->pricePerItemVatPercentage, 100, 6), 2), 1, 2);
        return $this;
    }

    public function calculatePricePerItemWithVat(): self
    {
        $this->pricePerItemWithVat = bcadd($this->pricePerItemWithoutVat, $this->pricePerItemVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getPricePerItemWithoutVat(): string
    {
        return $this->pricePerItemWithoutVat;
    }

    public function setPricePerItemWithoutVat(string $pricePerItemWithoutVat): self
    {
        $this->pricePerItemWithoutVat = $pricePerItemWithoutVat;
        return $this;
    }

    public function getPricePerItemVatPercentage(): string
    {
        return $this->pricePerItemVatPercentage;
    }

    public function setPricePerItemVatPercentage(string $pricePerItemVatPercentage): self
    {
        $this->pricePerItemVatPercentage = $pricePerItemVatPercentage;
        return $this;
    }

    public function getPricePerItemVatAmount(): string
    {
        return $this->pricePerItemVatAmount;
    }

    public function setPricePerItemVatAmount(string $priceVatAmount): self
    {
        $this->pricePerItemVatAmount = $priceVatAmount;
        return $this;
    }

    public function getPricePerItemWithVat(): string
    {
        return $this->pricePerItemWithVat;
    }

    public function setPricePerItemWithVat(string $pricePerItemWithVat): self
    {
        $this->pricePerItemWithVat = $pricePerItemWithVat;
        return $this;
    }
}
