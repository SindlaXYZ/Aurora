<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait PriceTrait
{
    #[ORM\Column(name: 'price_without_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price with VAT'])]
    #[FormElement(searchable: true)]
    #[Assert\GreaterThan(0, message: 'Price with VAT must be greater than 0.')]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $priceWithoutVat = '0.00';

    #[ORM\Column(name: 'vat_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '19.00', 'comment' => 'VAT amount (as percentage)'])]
    #[FormElement(searchable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $vatPercentage = '0.00';

    #[ORM\Column(name: 'vat_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'comment' => 'VAT Amount'])]
    #[FormElement(searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private string $vatAmount = '0.00';

    #[ORM\Column(name: 'price_with_vat', type: Types::DECIMAL, precision: 13, scale: 2, nullable: false, options: ['unsigned' => true, 'default' => '0.00', 'comment' => 'Price with VAT'])]
    #[FormElement(searchable: true)]
    #[Assert\GreaterThan(0, message: 'Price with VAT must be greater than 0.')]
    private string $priceWithVat = '0.00';

    public function getPriceWithoutVat(): string
    {
        return $this->priceWithoutVat;
    }

    public function setPriceWithoutVat(string $priceWithoutVat): self
    {
        $this->priceWithoutVat = $priceWithoutVat;
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

    public function calculateVatAmount(): self
    {
        $this->vatAmount = bcdiv(bcmul($this->priceWithoutVat, bcdiv($this->vatPercentage, 100, 2), 2), 1, 2);
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

    public function calculatePriceWithVat(): self
    {
        $this->priceWithVat = bcadd($this->priceWithoutVat, $this->vatAmount, 2);
        return $this;
    }
}
