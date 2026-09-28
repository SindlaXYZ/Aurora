<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\AmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer\BankTransferAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\BankTransfer\BankTransferDiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card\CardAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Card\CardDiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash\CashAmountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\Cash\CashDiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\DiscountPerItemTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\DiscountTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PricePerItemTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\ECommerce\PriceTrait;

class FormElementTest extends TestCase
{
    public function testDefaults(): void
    {
        $formElement = new FormElement();

        $this->assertFalse($formElement->searchable);
        $this->assertNull($formElement->label);
    }

    /**
     * @param class-string $trait
     */
    #[DataProvider('dataECommerceTraits')]
    public function testTheECommerceFieldsAreSearchableFormElements(string $trait): void
    {
        $properties = new \ReflectionClass($trait)->getProperties();
        $this->assertNotEmpty($properties);

        foreach ($properties as $property) {
            $attributes = $property->getAttributes(FormElement::class);
            $this->assertCount(1, $attributes, sprintf('The "%s" property is not a form element.', $property->getName()));

            $formElement = $attributes[0]->newInstance();
            $this->assertTrue($formElement->searchable, sprintf('The "%s" property is not searchable.', $property->getName()));
            $this->assertNotEmpty($formElement->label, sprintf('The "%s" property has no label.', $property->getName()));
        }
    }

    public static function dataECommerceTraits(): iterable
    {
        foreach ([
            AmountTrait::class, DiscountTrait::class, DiscountPerItemTrait::class, PriceTrait::class, PricePerItemTrait::class, CardAmountTrait::class,
            CardDiscountTrait::class, CashAmountTrait::class, CashDiscountTrait::class, BankTransferAmountTrait::class, BankTransferDiscountTrait::class,
        ] as $trait) {
            yield new \ReflectionClass($trait)->getShortName() => [$trait];
        }
    }

    public function testTheLabelIsKept(): void
    {
        $labels = [];
        foreach (new \ReflectionClass(DiscountTrait::class)->getProperties() as $property) {
            $labels[$property->getName()] = $property->getAttributes(FormElement::class)[0]->newInstance()->label;
        }

        $this->assertSame(['discountAmount' => 'Discount (fixed amount)', 'discountPercentage' => 'Discount % (percentage)'], $labels);
    }

    public function testOnlyAPropertyCanBeAFormElement(): void
    {
        $attribute = new \ReflectionObject(new #[FormElement] class {
        })->getAttributes(FormElement::class)[0];

        $this->expectException(\Error::class);
        $this->expectExceptionMessage(sprintf('Attribute "%s" cannot target class (allowed targets: property)', FormElement::class));

        $attribute->newInstance();
    }
}
