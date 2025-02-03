<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait DiscountTrait
{
    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Discount amount (fixed amount)'])]
    #[Assert\Range(min: 0)]
    #[FormElement(label: 'Discount (fixed amount)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $discountAmount = null;

    #[ORM\Column(name: 'discount_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Discount amount (as percentage)'])]
    #[Assert\Range(min: 0, max: 100)]
    #[FormElement(label: 'Discount % (percentage)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $discountPercentage = null;

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): self
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function calculateDiscountAmount(string $amount, ?string $discountPercentage = null): self
    {
        if (!($discountPercentage = $discountPercentage ?? $this->discountPercentage)) {
            throw new \Exception('Discount percentage is required to calculate discount amount');
        }

        $this->discountAmount = bcdiv(bcmul($amount, $discountPercentage, 2), 100, 2);
        return $this;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): self
    {
        $this->discountPercentage = $discountPercentage;
        return $this;
    }
}
