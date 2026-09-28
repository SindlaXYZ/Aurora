<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
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
        // Decimal strings are always truthy (including '0.00'), so they are compared with bccomp()
        if (0 !== bccomp($this->priceWithVat, '0', 2)) {
            // Without VAT = with VAT / (1 + VAT%), or with VAT - VAT amount when there is no VAT%: the VAT amount is the result of a previous
            // calculation, stale once the price with VAT changes (119.00 with the VAT 10.00 of 52.63 used to be 109.00 without VAT)
            $this->priceWithoutVat = (0 !== bccomp($this->priceVatPercentage, '0', 2))
                ? bcround(bcdiv($this->priceWithVat, bcadd('1', bcdiv($this->priceVatPercentage, '100', 6), 6), 10), 2)
                : bcsub($this->priceWithVat, $this->priceVatAmount, 2);
        } else if (0 !== bccomp($this->priceVatAmount, '0', 2) && 0 !== bccomp($this->priceVatPercentage, '0', 2)) {
            // Without VAT = VAT amount / VAT%
            $this->priceWithoutVat = bcround(bcdiv($this->priceVatAmount, bcdiv($this->priceVatPercentage, '100', 6), 10), 2);
        }

        return $this;
    }

    public function calculatePriceVatAmount(): self
    {
        // Rounded to the cent (half away from zero): bcmath truncates, e.g. the VAT of 10.99 at 19% was 2.08 instead of 2.09
        $this->priceVatAmount = bcround(bcmul($this->priceWithoutVat, bcdiv($this->priceVatPercentage, '100', 6), 10), 2);
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
