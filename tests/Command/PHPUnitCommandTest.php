<?php
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\ParameterBag;

if (!interface_exists(ParameterBagInterface::class)) {
    interface ParameterBagInterface
    {
        public function get(string $name);
    }
}

namespace Symfony\Component\Console\Command;

if (!class_exists(Command::class)) {
    abstract class Command
    {
        public const SUCCESS = 0;
        public const FAILURE = 1;

        protected ?string $name;

        public function __construct(?string $name = null)
        {
            $this->name = $name;

            if (method_exists($this, 'configure')) {
                $this->configure();
            }
        }

        public function setHelp(?string $help): static
        {
            return $this;
        }

        public function addOption(
            string $name,
            string|array|null $shortcut = null,
            ?int $mode = null,
            string $description = '',
            mixed $default = null
        ): static {
            return $this;
        }

        public function getName(): ?string
        {
            return $this->name;
        }
    }
}

namespace Symfony\Component\Finder;

if (!class_exists(Finder::class)) {
    class Finder implements \IteratorAggregate
    {
        private bool $onlyFiles = false;
        private ?string $directory = null;
        private ?string $namePattern = null;
        /** @var SplFileInfo[] */
        private array $files = [];

        public function files(): self
        {
            $this->onlyFiles = true;
            return $this;
        }

        public function in(string $directory): self
        {
            $this->directory = realpath($directory) ?: $directory;
            $this->refresh();

            return $this;
        }

        public function name(string $pattern): self
        {
            $this->namePattern = $pattern;
            $this->refresh();

            return $this;
        }

        public function hasResults(): bool
        {
            return !empty($this->files);
        }

        public function getIterator(): \Traversable
        {
            return new \ArrayIterator($this->files);
        }

        private function refresh(): void
        {
            if ($this->directory === null || !is_dir($this->directory)) {
                $this->files = [];
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS)
            );

            $files = [];

            foreach ($iterator as $file) {
                if ($this->onlyFiles && !$file->isFile()) {
                    continue;
                }

                if ($this->namePattern !== null && !$this->matchesPattern($file->getFilename())) {
                    continue;
                }

                $files[] = new SplFileInfo($file->getPathname(), $this->directory);
            }

            $this->files = $files;
        }

        private function matchesPattern(string $filename): bool
        {
            if ($this->namePattern === null) {
                return true;
            }

            $escaped = str_replace(['*', '?'], ['.*', '.'], preg_quote($this->namePattern, '/'));
            $regex   = '/^' . $escaped . '$/i';

            return (bool) preg_match($regex, $filename);
        }
    }

    class SplFileInfo
    {
        public function __construct(
            private readonly string $pathname,
            private readonly string $baseDirectory
        ) {
        }

        public function getRealPath(): string
        {
            return $this->pathname;
        }

        public function getFilename(): string
        {
            return basename($this->pathname);
        }

        public function getRelativePathname(): string
        {
            $base = rtrim($this->baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (str_starts_with($this->pathname, $base)) {
                return substr($this->pathname, strlen($base));
            }

            return $this->pathname;
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Command\PHPUnitCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class PHPUnitCommandTest extends TestCase
{
    #[Test]
    public function testUpdateDocumentationBlocksKeepsPhpAttributesAttached(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/aurora_phpunit_' . uniqid('', true);
        $testsRoot     = $temporaryRoot . '/tests/Integration';

        self::assertTrue(mkdir($testsRoot, 0777, true), 'Failed to create the temporary tests directory.');

        $sourceFile = $testsRoot . '/Given.php';
        $fixture    = __DIR__ . '/PHPUnit/Given.php';
        $expected   = __DIR__ . '/PHPUnit/Expected.php';

        $original = file_get_contents($fixture);
        self::assertNotFalse($original, 'Fixture content could not be read.');
        self::assertTrue(file_put_contents($sourceFile, $original) !== false, 'Could not create the working test file.');

        $parameterBag = new ParameterBag(['kernel.project_dir' => $temporaryRoot]);

        try {
            $command = new PHPUnitCommand(null, $parameterBag);

            $input  = new ArrayInput([]);
            $output = new BufferedOutput();

            $initialize = new \ReflectionMethod(CommandMiddleware::class, 'initialize');
            $initialize->setAccessible(true);
            $initialize->invoke($command, $input, $output);

            $method = new \ReflectionMethod(PHPUnitCommand::class, 'updateDocumentationBlocks');
            $method->setAccessible(true);

            self::assertSame(
                PHPUnitCommand::SUCCESS,
                $method->invoke($command),
                'The command should finish successfully.'
            );

            $result          = file_get_contents($sourceFile);
            $expectedContent = file_get_contents($expected);

            self::assertNotFalse($result, 'The updated file could not be read.');
            self::assertNotFalse($expectedContent, 'The expected file could not be read.');
            self::assertSame($expectedContent, $result, 'The updated file does not match the expected content.');
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
