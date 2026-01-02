<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer;

use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait BankTransferDiscountTrait
{
    #[ORM\Column(name: 'bank_transfer_discount_amount', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Bank transfer discount amount (fixed amount)'])]
    #[Assert\Range(min: 0)]
    #[FormElement(label: 'Bank transfer discount (fixed amount)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $bankTransferDiscountAmount = null;

    #[ORM\Column(name: 'bank_transfer_discount_percentage', type: Types::DECIMAL, precision: 13, scale: 2, nullable: true, options: ['unsigned' => true, 'default' => null, 'comment' => 'Bank transfer discount amount (as percentage)'])]
    #[Assert\Range(min: 0, max: 100)]
    #[FormElement(label: 'Bank transfer discount % (percentage)', searchable: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $bankTransferDiscountPercentage = null;

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // -- Custom logic -- --------------------------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @throws \Exception
     */
    public function calculateCashDiscountAmount(string $amount, ?string $discountPercentage = null): self
    {
        if (!($discountPercentage = $discountPercentage ?? $this->bankTransferDiscountPercentage)) {
            throw new \Exception('Bank transfer discount percentage is required to calculate discount amount');
        }

        $this->bankTransferDiscountAmount = bcdiv(bcmul($amount, $discountPercentage, 2), 100, 2);
        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

    public function getBankTransferDiscountAmount(): ?string
    {
        return $this->bankTransferDiscountAmount;
    }

    public function setBankTransferDiscountAmount(?string $bankTransferDiscountAmount): self
    {
        $this->bankTransferDiscountAmount = $bankTransferDiscountAmount;
        return $this;
    }

    public function getBankTransferDiscountPercentage(): ?string
    {
        return $this->bankTransferDiscountPercentage;
    }

    public function setBankTransferDiscountPercentage(?string $bankTransferDiscountPercentage): self
    {
        $this->bankTransferDiscountPercentage = $bankTransferDiscountPercentage;
        return $this;
    }
}
