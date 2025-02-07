<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash;

use App\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait CashDiscountTrait
{
    #[ORM\Column(name: 'cash_discount_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Cash discount amount (fixed amount)'])]
    #[Assert\Range(min: 0)]
    #[FormElement(label: 'Cash discount (fixed amount)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $cashDiscountAmount = null;

    #[ORM\Column(name: 'cash_discount_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Cash discount amount (as percentage)'])]
    #[Assert\Range(min: 0, max: 100)]
    #[FormElement(label: 'Cash discount % (percentage)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $cashDiscountPercentage = null;

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    public function calculateCashDiscountAmount(string $amount, ?string $discountPercentage = null): self
    {
        if (!($discountPercentage = $discountPercentage ?? $this->cashDiscountPercentage)) {
            throw new \Exception('Discount percentage is required to calculate discount amount');
        }

        $this->cashDiscountAmount = bcdiv(bcmul($amount, $discountPercentage, 2), 100, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getCashDiscountAmount(): ?string
    {
        return $this->cashDiscountAmount;
    }

    public function setCashDiscountAmount(?string $cashDiscountAmount): self
    {
        $this->cashDiscountAmount = $cashDiscountAmount;
        return $this;
    }

    public function getCashDiscountPercentage(): ?string
    {
        return $this->cashDiscountPercentage;
    }

    public function setCashDiscountPercentage(?string $cashDiscountPercentage): self
    {
        $this->cashDiscountPercentage = $cashDiscountPercentage;
        return $this;
    }
}
