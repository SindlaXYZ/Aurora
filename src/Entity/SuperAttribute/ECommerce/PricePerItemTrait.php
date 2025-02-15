<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait PricePerItemTrait
{
    #[ORM\Column(name: 'price_per_item_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price per item without VAT'])]
    #[FormElement(searchable: true, label: 'Price per item without VAT')]
    #[Assert\GreaterThan(0, message: 'Price with VAT must be greater than 0.')]
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
    #[Assert\GreaterThan(0, message: 'Price per item with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $pricePerItemWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculatePricePerItemWithoutVat(): self
    {
        if ($this->pricePerItemWithoutVat) {
            $this->pricePerItemWithoutVat = bcsub($this->pricePerItemWithoutVat, $this->pricePerItemVatAmount, 2);
        } else if ($this->pricePerItemVatAmount) {
            $this->pricePerItemWithoutVat = bcdiv($this->pricePerItemVatAmount, bcadd(1, bcdiv($this->pricePerItemVatPercentage, 100, 2), 2), 2);
        }

        return $this;
    }

    public function calculatePricePerItemVatAmount(): self
    {
        $this->pricePerItemVatAmount = bcdiv(bcmul($this->pricePerItemWithoutVat, bcdiv($this->pricePerItemVatPercentage, 100, 2), 2), 1, 2);
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
