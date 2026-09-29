<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\AuroraBundle;
use Sindla\Bundle\AuroraBundle\Command\CloudflareR2Command;
use Sindla\Bundle\AuroraBundle\Command\ComposerCommand;
use Sindla\Bundle\AuroraBundle\Command\LazyEntityCommand;
use Sindla\Bundle\AuroraBundle\Command\PHPUnitCommand;
use Sindla\Bundle\AuroraBundle\Command\TestCommand;
use Sindla\Bundle\AuroraBundle\Console\SymfonyStyleFactory;
use Sindla\Bundle\AuroraBundle\Controller\CompiledController;
use Sindla\Bundle\AuroraBundle\Controller\CustomExceptionController;
use Sindla\Bundle\AuroraBundle\Controller\PWAController;
use Sindla\Bundle\AuroraBundle\DependencyInjection\ExtraLoader;
use Sindla\Bundle\AuroraBundle\EventSubscriber\SoftDeleteIndexSubscriber;
use Sindla\Bundle\AuroraBundle\EventSubscriber\TraitLifecycleCallbacksSubscriber;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Sindla\Bundle\AuroraBundle\Utils\AuroraStrink\AuroraStrink;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Yaml\Parser;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class AuroraBundleTest extends TestCase
{
    public function testTheBundleKeepsTheResourcesDirectoryStructure(): void
    {
        $bundle = new AuroraBundle();

        $this->assertInstanceOf(AbstractBundle::class, $bundle);
        // AbstractBundle assumes the root of the package: the applications import "@AuroraBundle/Resources/config/routes/routes.yaml"
        // and TwigBundle registers src/templates/ as the "@Aurora" namespace
        $this->assertSame(dirname(__DIR__) . '/src', $bundle->getPath());
        $this->assertFileExists($bundle->getPath() . '/Resources/config/routes/routes.yaml');
        $this->assertFileExists($bundle->getPath() . '/templates/offline.html.twig');
    }

    public function testTheContainerExtensionIsNamedAfterTheBundle(): void
    {
        // The "aurora" configuration key of the host application
        $this->assertSame('aurora', new AuroraBundle()->getContainerExtension()->getAlias());
    }

    public function testTheBundlesUsedByTheServicesAreRequired(): void
    {
        $required = array_map(
            static fn(\ReflectionAttribute $attribute): string => $attribute->newInstance()->class,
            new \ReflectionClass(AuroraBundle::class)->getAttributes(RequiredBundle::class)
        );

        // The kernel registers them when config/bundles.php does not (Symfony 8.1)
        $this->assertSame([FrameworkBundle::class, TwigBundle::class], $required);
    }

    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheAuroraKeyHasNoOptions(): void
    {
        // The settings are container parameters ("parameters: aurora.*"): an empty "aurora" key is valid, an option is not
        $this->loadServices([[]]);

        $this->expectException(InvalidConfigurationException::class);

        $this->loadServices([['bundle' => 'App']]);
    }

    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheServicesAreNeitherAutowiredNorAutoconfigured(): void
    {
        $definitions = $this->loadServices()->getDefinitions();
        unset($definitions['service_container']);

        $this->assertArrayHasKey('aurora.helper', $definitions);

        foreach ($definitions as $id => $definition) {
            if (!$definition->isAbstract()) {
                $class = $definition->getClass() ?? $id;
                $this->assertTrue(class_exists($class), sprintf('The class "%s" of the service "%s" does not exist.', $class, $id));
            }

            // https://symfony.com/doc/current/bundles/best_practices.html#services
            $this->assertFalse($definition->isAutowired(), sprintf('The service "%s" is autowired.', $id));
            $this->assertFalse($definition->isAutoconfigured(), sprintf('The service "%s" is autoconfigured.', $id));
        }
    }

    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheServiceIdsArePrefixedWithTheAliasOfTheBundle(): void
    {
        $definitions = $this->loadServices()->getDefinitions();
        unset($definitions['service_container']);

        foreach (array_keys($definitions) as $id) {
            // A service of the application registered by its class used to replace the definition of the bundle (and its tags)
            $this->assertStringStartsWith('aurora.', $id);
        }
    }

    /**
     * The explicit arguments match the constructors (they used to be autowired)
     */
    #[RequiresMethod(Parser::class, 'parse')]
    public function testEveryServiceCanBeCreated(): void
    {
        $container = $this->loadServices();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->set('parameter_bag', new ParameterBag(['kernel.environment' => 'test', 'aurora.root' => sys_get_temp_dir()]));
        $container->set('request_stack', new RequestStack());
        $container->set('twig', new Environment(new ArrayLoader()));
        new ResolveChildDefinitionsPass()->process($container);

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            $this->assertInstanceOf($definition->getClass(), $container->get($id), sprintf('The service "%s" cannot be created.', $id));
        }
    }

    /**
     * They are fetched from the container by id (ComposerCommand, UtilityExtension, host apps)
     */
    #[DataProvider('dataPublicServices')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheServicesFetchedByIdArePublic(string $id): void
    {
        $this->assertTrue($this->loadServices()->getDefinition($id)->isPublic());
    }

    public static function dataPublicServices(): iterable
    {
        foreach (['aurora.client', 'aurora.git', 'aurora.io', 'aurora.pwa', 'aurora.sanitizer', 'aurora.twig.utility'] as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('dataAutowiringAliases')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheServicesAreAliasedByTheirClass(string $class, string $id): void
    {
        $this->assertSame($id, (string)$this->loadServices()->getAlias($class));
    }

    public static function dataAutowiringAliases(): iterable
    {
        yield 'client' => [AuroraClient::class, 'aurora.client'];
        yield 'PWA' => [AuroraPWA::class, 'aurora.pwa'];
        yield 'Strink' => [AuroraStrink::class, 'aurora.strink'];
        yield 'Twig extension' => [UtilityExtension::class, 'aurora.twig.utility'];
        yield 'console style' => [SymfonyStyle::class, 'aurora.console.symfony_style'];
        yield 'console style factory' => [SymfonyStyleFactory::class, 'aurora.console.symfony_style_factory'];
        // The former ids
        yield 'soft-delete index subscriber' => [SoftDeleteIndexSubscriber::class, 'aurora.doctrine.soft_delete_index_subscriber'];
        yield 'lifecycle callbacks subscriber' => [TraitLifecycleCallbacksSubscriber::class, 'aurora.doctrine.trait_lifecycle_callbacks_subscriber'];
        yield 'route loader of the "extra" routes' => [ExtraLoader::class, 'aurora.routing.extra_loader'];
    }

    #[RequiresMethod(Parser::class, 'parse')]
    public function testEveryPublicServiceHasAnAutowiringAlias(): void
    {
        $container = $this->loadServices();

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isPublic() && str_starts_with($id, 'aurora.')) {
                $this->assertTrue($container->hasAlias($definition->getClass()), sprintf('The service "%s" has no autowiring alias.', $id));
                $this->assertSame($id, (string)$container->getAlias($definition->getClass()));
            }
        }
    }

    /**
     * A controller referenced by its class (e.g. "framework.error_controller") is fetched from the container by the controller resolver
     */
    #[DataProvider('dataControllers')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheControllersCanBeReferencedByTheirClass(string $class, string $id): void
    {
        $container = $this->loadServices();

        $this->assertSame($id, (string)$container->getAlias($class));
        $this->assertTrue($container->getAlias($class)->isPublic());
        $this->assertSame([[]], $container->getDefinition($id)->getTag('controller.service_arguments'));
    }

    public static function dataControllers(): iterable
    {
        yield 'PWA' => [PWAController::class, 'aurora.controller.pwa'];
        yield 'compiled CSS / JS' => [CompiledController::class, 'aurora.controller.compiled'];
        yield 'error page' => [CustomExceptionController::class, 'aurora.controller.custom_exception'];
    }

    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheStrinkBuilderIsNotShared(): void
    {
        $container = $this->loadServices();

        // A stateful fluent builder: every consumer gets its own instance
        $this->assertNotSame($container->get('aurora.strink'), $container->get('aurora.strink'));
    }

    /**
     * @param array<int, array<string, mixed>> $expectedAttributes
     */
    #[DataProvider('dataTaggedServices')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheServicesAreTaggedOnce(string $id, string $tag, array $expectedAttributes): void
    {
        $this->assertSame($expectedAttributes, $this->loadServices()->getDefinition($id)->getTag($tag));
    }

    public static function dataTaggedServices(): iterable
    {
        yield 'route loader of the "extra" routes' => ['aurora.routing.extra_loader', 'routing.loader', [[]]];
        // The #[AsDoctrineListener] attribute used to register it a second time
        yield 'soft-delete index subscriber' => ['aurora.doctrine.soft_delete_index_subscriber', 'doctrine.event_listener', [['event' => 'loadClassMetadata']]];
        yield 'lifecycle callbacks subscriber' => ['aurora.doctrine.trait_lifecycle_callbacks_subscriber', 'doctrine.event_listener', [['event' => 'loadClassMetadata']]];
        yield 'Twig extension' => ['aurora.twig.utility', 'twig.extension', [[]]];
        // A new CSP nonce for each request of a long-running process
        yield 'Twig extension reset' => ['aurora.twig.utility', 'kernel.reset', [['method' => 'reset']]];

        foreach (['cloudflare.r2', 'test', 'lazy_entity', 'php_unit', 'composer'] as $command) {
            // The name comes from the #[AsCommand] attribute; autoconfiguration used to add a second "console.command" tag
            yield sprintf('command %s', $command) => [sprintf('aurora.command.%s', $command), 'console.command', [[]]];
        }
    }

    /**
     * "aurora:composer" was also an alias of "aurora:lazy.entity", and every command was registered under a "name|alias" name too
     */
    #[RequiresMethod(Parser::class, 'parse')]
    public function testEveryCommandNameAndAliasBelongsToOneCommand(): void
    {
        $container = $this->loadServices();
        new ResolveChildDefinitionsPass()->process($container);
        new AddConsoleCommandPass()->process($container);

        $this->assertSame(
            [
                'aurora:cloudflare:r2' => 'aurora.command.cloudflare.r2',
                'aurora:test'          => 'aurora.command.test',
                'aurora:lazy.entity'   => 'aurora.command.lazy_entity',
                'aurora:php-unit'      => 'aurora.command.php_unit',
                'aurora:phpunit'       => 'aurora.command.php_unit',
                'aurora:composer'      => 'aurora.command.composer',
            ],
            $container->getDefinition('console.command_loader')->getArgument(1)
        );
    }

    #[DataProvider('dataCommandMiddlewareCommands')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheCommandMiddlewareDependenciesAreInjected(string $id, string $class): void
    {
        $container = $this->loadServices();
        new ResolveChildDefinitionsPass()->process($container);

        $definition = $container->getDefinition($id);

        $this->assertSame($class, $definition->getClass());
        // Its #[Required] setters used to be autowired
        $this->assertEquals(
            [
                ['setManagerRegistry', [new Reference('doctrine', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]],
                ['setEntityManager', [new Reference('doctrine.orm.entity_manager', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]],
                ['setProjectDir', ['%kernel.project_dir%']],
            ],
            $definition->getMethodCalls()
        );
    }

    public static function dataCommandMiddlewareCommands(): iterable
    {
        yield 'Cloudflare R2' => ['aurora.command.cloudflare.r2', CloudflareR2Command::class];
        yield 'test' => ['aurora.command.test', TestCommand::class];
        yield 'PHPUnit' => ['aurora.command.php_unit', PHPUnitCommand::class];
    }

    #[DataProvider('dataContainerCommands')]
    #[RequiresMethod(Parser::class, 'parse')]
    public function testTheOtherCommandsHaveNoMiddlewareDependencies(string $id, string $class): void
    {
        $definition = $this->loadServices()->getDefinition($id);

        $this->assertSame($class, $definition->getClass());
        $this->assertSame([], $definition->getMethodCalls());
    }

    public static function dataContainerCommands(): iterable
    {
        yield 'composer' => ['aurora.command.composer', ComposerCommand::class];
        yield 'lazy entity' => ['aurora.command.lazy_entity', LazyEntityCommand::class];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function loadServices(array $configs = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.environment' => 'test']));

        new AuroraBundle()->getContainerExtension()->load($configs, $container);

        return $container;
    }
}
