<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\DependencyInjection\AuroraExtension;
use Sindla\Bundle\AuroraBundle\DependencyInjection\ExtraLoader;
use Sindla\Bundle\AuroraBundle\EventSubscriber\SoftDeleteIndexSubscriber;
use Sindla\Bundle\AuroraBundle\EventSubscriber\TraitLifecycleCallbacksSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Parser;

#[RequiresMethod(Parser::class, 'parse')]
class AuroraExtensionTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        new AuroraExtension()->load([], $this->container);
    }

    public function testLoadRegistersTheServicesOfTheBundle(): void
    {
        $definitions = $this->container->getDefinitions();
        unset($definitions['service_container']);

        $this->assertArrayHasKey('aurora.helper', $definitions);

        foreach ($definitions as $id => $definition) {
            $class = $definition->getClass() ?? $id;

            $this->assertTrue(class_exists($class), sprintf('The class "%s" of the service "%s" does not exist.', $class, $id));
            // The "_defaults" of services.yaml
            $this->assertTrue($definition->isAutowired(), sprintf('The service "%s" is not autowired.', $id));
            $this->assertTrue($definition->isAutoconfigured(), sprintf('The service "%s" is not autoconfigured.', $id));
        }
    }

    /**
     * They are fetched from the container by id (PWAController, ComposerCommand, UtilityExtension, host apps)
     */
    #[DataProvider('dataPublicServices')]
    public function testTheServicesFetchedByIdArePublic(string $id): void
    {
        $this->assertTrue($this->container->getDefinition($id)->isPublic());
    }

    public static function dataPublicServices(): iterable
    {
        foreach (['aurora.client', 'aurora.git', 'aurora.io', 'aurora.pwa', 'aurora.sanitizer', 'aurora.twig.utility'] as $id) {
            yield $id => [$id];
        }
    }

    public function testTheStrinkBuilderIsNotShared(): void
    {
        // A stateful fluent builder: every consumer gets its own instance
        $this->assertNotSame($this->container->get('aurora.strink'), $this->container->get('aurora.strink'));
    }

    /**
     * @param array<int, array<string, mixed>> $expectedAttributes
     */
    #[DataProvider('dataTaggedServices')]
    public function testTheServicesAreTagged(string $id, string $tag, array $expectedAttributes): void
    {
        $this->assertSame($expectedAttributes, $this->container->getDefinition($id)->getTag($tag));
    }

    public static function dataTaggedServices(): iterable
    {
        yield 'route loader of the "extra" routes' => [ExtraLoader::class, 'routing.loader', [[]]];
        yield 'soft-delete index subscriber' => [SoftDeleteIndexSubscriber::class, 'doctrine.event_listener', [['event' => 'loadClassMetadata']]];
        yield 'lifecycle callbacks subscriber' => [TraitLifecycleCallbacksSubscriber::class, 'doctrine.event_listener', [['event' => 'loadClassMetadata']]];
        yield 'Twig extension' => ['aurora.twig.utility', 'twig.extension', [[]]];
        yield 'PHPUnit command' => ['aurora.command.php_unit', 'console.command', [[]]];
    }
}
