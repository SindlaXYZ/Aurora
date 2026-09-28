<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\LazyEntityCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The getter / setter generation itself is not covered: it reads the entities with the Doctrine annotation reader, a package that is not a
 * dependency of the bundle (doctrine/annotations; Doctrine ORM 3 has no annotation mapping at all)
 */
class LazyEntityCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/aurora_lazy_entity_' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->projectDir));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testConfigureDefinesTheCommand(): void
    {
        $command = $this->command();

        self::assertSame('aurora:lazy.entity', $command->getName());
        self::assertSame('Aurora entity generator', $command->getDescription());
        self::assertSame('This command allows you to autogenerate files and methods for an entity...', $command->getHelp());

        $definition = $command->getDefinition();
        self::assertSame(['namespace', 'sonataAdmin', 'entity', 'entityQualifiedName', 'eqn'], array_keys($definition->getOptions()));

        foreach ($definition->getOptions() as $option) {
            self::assertTrue($option->isValueRequired(), sprintf('The "--%s" option is expected to require a value.', $option->getName()));
        }

        self::assertSame([], $definition->getArguments());
    }

    public function testExecuteRequiresTheNamespace(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Option --namespace is not set.');

        new CommandTester($this->command())->execute(['--entity' => 'Product']);
    }

    public function testExecuteRequiresAnEntity(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Option --entityQualifiedName (--eqn)');

        new CommandTester($this->command())->execute(['--namespace' => 'Acme']);
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function dataUnknownEntityQualifiedName(): iterable
    {
        yield 'long option' => [['--entityQualifiedName' => 'Acme\Entity\Missing'], 'Class "Acme\Entity\Missing" does not exist'];
        yield 'short option' => [['--eqn' => 'Acme\Entity\Absent'], 'Class "Acme\Entity\Absent" does not exist'];
        yield 'the long option wins' => [['--entityQualifiedName' => 'Acme\Entity\Missing', '--eqn' => 'Acme\Entity\Absent'], 'Class "Acme\Entity\Missing" does not exist'];
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('dataUnknownEntityQualifiedName')]
    public function testExecuteRejectsAnUnknownEntityQualifiedName(array $input, string $expectedMessage): void
    {
        // The namespace is only required to locate an entity by its short name
        $this->expectExceptionObject(new \Exception($expectedMessage));

        new CommandTester($this->command())->execute($input);
    }

    public function testExecuteRequiresTheEntityDirectoryOfTheProject(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('(%s/src/Entity/)', $this->projectDir));

        new CommandTester($this->command())->execute(['--namespace' => 'Acme', '--entity' => 'Product']);
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: array<string, string>}>
     */
    public static function dataEntityFilesThatAreNotRegenerated(): iterable
    {
        yield 'not the requested entity' => [['Product.php', 'Catalog/Category.php'], ['--entity' => 'Brand']];
        // The traits shared by the entities are never regenerated, even when requested
        yield 'identifiable trait' => [['IdentifiableTrait.php', 'Product.php'], ['--entity' => 'IdentifiableTrait']];
        yield 'temporal trait' => [['Supers/TemporalTrait.php'], ['--entity' => 'TemporalTrait']];
    }

    /**
     * @param list<string>          $entityFiles
     * @param array<string, string> $input
     */
    #[DataProvider('dataEntityFilesThatAreNotRegenerated')]
    public function testExecuteLeavesTheSkippedEntityFilesUntouched(array $entityFiles, array $input): void
    {
        $contents = [];
        foreach ($entityFiles as $entityFile) {
            $path = sprintf('%s/src/Entity/%s', $this->projectDir, $entityFile);

            if (!is_dir(dirname($path))) {
                self::assertTrue(mkdir(dirname($path), 0777, true));
            }

            $contents[$path] = sprintf("<?php\n\nnamespace Acme\\Entity;\n\n// %s\n", $entityFile);
            file_put_contents($path, $contents[$path]);
        }

        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute(['--namespace' => 'Acme', '--sonataAdmin' => 'true'] + $input));
        self::assertSame('', $tester->getDisplay());

        foreach ($contents as $path => $content) {
            self::assertSame($content, file_get_contents($path));
        }

        // Neither a repository nor a Sonata admin class is generated
        self::assertSame(['src'], $this->listDirectory($this->projectDir));
        self::assertSame(['Entity'], $this->listDirectory($this->projectDir . '/src'));
    }

    private function command(): LazyEntityCommand
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->createStub(EntityManagerInterface::class));

        $container = $this->createStub(ContainerInterface::class);
        $container->method('getParameter')->willReturnMap([['kernel.project_dir', $this->projectDir]]);
        $container->method('get')->willReturnMap([['doctrine', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $registry]]);

        return new LazyEntityCommand($container);
    }

    /**
     * @return list<string>
     */
    private function listDirectory(string $directory): array
    {
        return array_values(array_diff((array)scandir($directory), ['.', '..']));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach ($this->listDirectory($directory) as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
