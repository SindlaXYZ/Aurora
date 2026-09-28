<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\DependencyInjection\AuroraExtension;
use Sindla\Bundle\AuroraBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConfigurationTest extends TestCase
{
    public function testTheTreeIsNamedAfterTheExtension(): void
    {
        $extension = new AuroraExtension();

        $this->assertSame($extension->getAlias(), new Configuration()->getConfigTreeBuilder()->buildTree()->getName());
        // Found by the Extension naming convention (config:dump-reference aurora, the env placeholders validation)
        $this->assertInstanceOf(Configuration::class, $extension->getConfiguration([], new ContainerBuilder()));
    }

    public function testAnEmptyConfigurationIsValid(): void
    {
        // The settings are container parameters ("parameters: aurora.*"), the "aurora" key has no options
        $this->assertSame([], new Processor()->processConfiguration(new Configuration(), [[]]));
    }
}
