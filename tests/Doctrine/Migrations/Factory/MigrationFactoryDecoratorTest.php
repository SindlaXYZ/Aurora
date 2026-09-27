<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Doctrine\Migrations\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sindla\Bundle\AuroraBundle\Doctrine\Migrations\Factory\MigrationFactoryDecorator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrationFactoryDecoratorTest extends TestCase
{
    public function testInjectsTheContainerIntoTheMigrationsThatAcceptIt(): void
    {
        $container = new Container();
        $migration = $this->createDecorator(MigrationFactoryDecoratorContainerAwareMigration::class, $container)
            ->createVersion(MigrationFactoryDecoratorContainerAwareMigration::class);

        // ContainerAwareInterface was removed in Symfony 7: the "instanceof" check was always false, so the container was never injected
        $this->assertInstanceOf(MigrationFactoryDecoratorContainerAwareMigration::class, $migration);
        $this->assertSame($container, $migration->container);
    }

    public function testLeavesTheOtherMigrationsUnchanged(): void
    {
        $migration = $this->createDecorator(MigrationFactoryDecoratorPlainMigration::class, new Container())
            ->createVersion(MigrationFactoryDecoratorPlainMigration::class);

        $this->assertInstanceOf(MigrationFactoryDecoratorPlainMigration::class, $migration);
    }

    /**
     * @param class-string<AbstractMigration> $migrationClassName
     */
    private function createDecorator(string $migrationClassName, ContainerInterface $container): MigrationFactoryDecorator
    {
        $migration = new $migrationClassName($this->createStub(Connection::class), new NullLogger());

        $factory = $this->createStub(MigrationFactory::class);
        $factory->method('createVersion')->willReturn($migration);

        return new MigrationFactoryDecorator($factory, $container);
    }
}

final class MigrationFactoryDecoratorContainerAwareMigration extends AbstractMigration
{
    public ?ContainerInterface $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function up(Schema $schema): void
    {
    }
}

final class MigrationFactoryDecoratorPlainMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
    }
}
