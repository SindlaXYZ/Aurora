<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CardDiscountTrait
{
    #[ORM\Column(name: 'card_discount_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Card discount amount (fixed amount)'])]
    #[Assert\Range(min: 0)]
    #[FormElement(label: 'Card discount (fixed amount)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $cardDiscountAmount = null;

    #[ORM\Column(name: 'card_discount_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Card discount amount (as percentage)'])]
    #[Assert\Range(min: 0, max: 100)]
    #[FormElement(label: 'Card discount % (percentage)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $cardDiscountPercentage = null;

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    public function calculateCardDiscountAmount(string $amount, ?string $discountPercentage = null): self
    {
        if (!($discountPercentage = $discountPercentage ?? $this->cardDiscountPercentage)) {
            throw new \Exception('Discount percentage is required to calculate discount amount');
        }

        $this->cardDiscountAmount = bcdiv(bcmul($amount, $discountPercentage, 2), 100, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getCardDiscountAmount(): ?string
    {
        return $this->cardDiscountAmount;
    }

    public function setCardDiscountAmount(?string $cardDiscountAmount): self
    {
        $this->cardDiscountAmount = $cardDiscountAmount;
        return $this;
    }

    public function getCardDiscountPercentage(): ?string
    {
        return $this->cardDiscountPercentage;
    }

    public function setCardDiscountPercentage(?string $cardDiscountPercentage): self
    {
        $this->cardDiscountPercentage = $cardDiscountPercentage;
        return $this;
    }
}
