<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Doctrine\Attributes;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Doctrine\Attributes\Aurora;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc\MetaTrait;

class AuroraTest extends TestCase
{
    public function testTheMetaIsAJsonProperty(): void
    {
        $entity = new class {
            use MetaTrait;
        };

        $attributes = new \ReflectionProperty($entity, 'meta')->getAttributes(Aurora::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(['json' => true], $attributes[0]->getArguments());
        // The named argument is a parameter of the constructor: an unknown one is an Error
        $this->assertInstanceOf(Aurora::class, $attributes[0]->newInstance());
    }

    public function testOnlyAPropertyCanBeMarked(): void
    {
        $attribute = new \ReflectionObject(new #[Aurora(toSting: true)] class {
        })->getAttributes(Aurora::class)[0];

        $this->expectException(\Error::class);
        $this->expectExceptionMessage(sprintf('Attribute "%s" cannot target class (allowed targets: property)', Aurora::class));

        $attribute->newInstance();
    }
}
