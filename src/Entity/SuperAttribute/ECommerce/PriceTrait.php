<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait PriceTrait
{
    #[ORM\Column(name: 'price_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price with VAT'])]
    #[FormElement(searchable: true, label: 'Price without VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $priceWithoutVat = '0.00';

    #[ORM\Column(name: 'price_vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'VAT amount (as percentage)'])]
    #[FormElement(searchable: true, label: 'VAT % (percentage)')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $priceVatPercentage = '0.00';

    #[ORM\Column(name: 'price_vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'VAT Amount'])]
    #[FormElement(searchable: true, label: 'VAT amount')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $priceVatAmount = '0.00';

    #[ORM\Column(name: 'price_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price with VAT'])]
    #[FormElement(searchable: true, label: 'Price with VAT')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $priceWithVat = '0.00';

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function calculatePriceWithoutVat(): self
    {
        if ($this->priceWithoutVat) {
            $this->priceWithoutVat = bcsub($this->priceWithoutVat, $this->priceVatAmount, 2);
        } else if ($this->priceVatAmount) {
            $this->priceWithoutVat = bcdiv($this->priceVatAmount, bcadd(1, bcdiv($this->priceVatPercentage, 100, 2), 2), 2);
        }

        return $this;
    }

    public function calculatePriceVatAmount(): self
    {
        $this->priceVatAmount = bcdiv(bcmul($this->priceWithoutVat, bcdiv($this->priceVatPercentage, 100, 2), 2), 1, 2);
        return $this;
    }

    public function calculatePriceWithVat(): self
    {
        $this->priceWithVat = bcadd($this->priceWithoutVat, $this->priceVatAmount, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getPriceWithoutVat(): string
    {
        return $this->priceWithoutVat;
    }

    public function setPriceWithoutVat(string $priceWithoutVat): self
    {
        $this->priceWithoutVat = $priceWithoutVat;
        return $this;
    }

    public function getPriceVatPercentage(): string
    {
        return $this->priceVatPercentage;
    }

    public function setPriceVatPercentage(string $priceVatPercentage): self
    {
        $this->priceVatPercentage = $priceVatPercentage;
        return $this;
    }

    public function getPriceVatAmount(): string
    {
        return $this->priceVatAmount;
    }

    public function setPriceVatAmount(string $priceVatAmount): self
    {
        $this->priceVatAmount = $priceVatAmount;
        return $this;
    }

    public function getPriceWithVat(): string
    {
        return $this->priceWithVat;
    }

    public function setPriceWithVat(string $priceWithVat): self
    {
        $this->priceWithVat = $priceWithVat;
        return $this;
    }
}
