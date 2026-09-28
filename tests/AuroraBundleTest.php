<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\AuroraBundle;
use Sindla\Bundle\AuroraBundle\DependencyInjection\AuroraExtension;

class AuroraBundleTest extends TestCase
{
    public function testTheContainerExtensionIsTheAuroraExtension(): void
    {
        $extension = new AuroraBundle()->getContainerExtension();

        $this->assertInstanceOf(AuroraExtension::class, $extension);
        // The "aurora" configuration key of the host application
        $this->assertSame('aurora', $extension->getAlias());
    }
}
