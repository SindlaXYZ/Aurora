<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait DiscountPerItemTrait
{
    #[ORM\Column(name: 'discount_per_item_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Discount per item amount (fixed amount)'])]
    #[Assert\Range(min: 0)]
    #[FormElement(label: 'Discount per item (fixed amount)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $discountPerItemAmount = null;

    #[ORM\Column(name: 'discount_per_item_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Discount per item (as percentage)'])]
    #[Assert\Range(min: 0, max: 100)]
    #[FormElement(label: 'Discount per item % (percentage)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $discountPerItemPercentage = null;

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    public function calculateDiscountPerItemAmount(string $amount, ?string $discountPercentage = null): self
    {
        if (!($discountPercentage = $discountPercentage ?? $this->discountPerItemPercentage)) {
            throw new \Exception('Discount percentage is required to calculate discount amount');
        }

        // Rounded to the cent (half away from zero): bcmath truncates, e.g. 15% of 10.99 was 1.64 instead of 1.65
        $this->discountPerItemAmount = bcround(bcdiv(bcmul($amount, $discountPercentage, 10), '100', 10), 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getDiscountPerItemAmount(): ?string
    {
        return $this->discountPerItemAmount;
    }

    public function setDiscountPerItemAmount(?string $discountPerItemAmount): self
    {
        $this->discountPerItemAmount = $discountPerItemAmount;
        return $this;
    }

    public function getDiscountPerItemPercentage(): ?string
    {
        return $this->discountPerItemPercentage;
    }

    public function setDiscountPerItemPercentage(?string $discountPerItemPercentage): self
    {
        $this->discountPerItemPercentage = $discountPerItemPercentage;
        return $this;
    }
}
