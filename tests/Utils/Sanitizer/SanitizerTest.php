<?php

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface
        {
            public const RUNTIME_EXCEPTION_ON_INVALID_REFERENCE = 0;
            public const EXCEPTION_ON_INVALID_REFERENCE = 1;
            public const NULL_ON_INVALID_REFERENCE = 2;
            public const IGNORE_ON_INVALID_REFERENCE = 3;
            public const IGNORE_ON_UNINITIALIZED_REFERENCE = 4;

            public function set(string $id, ?object $service): void;

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object;

            public function has(string $id): bool;

            public function initialized(string $id): bool;

            /**
             * @return array<int|string, mixed>|bool|float|int|string|\UnitEnum|null
             */
            public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null;

            public function hasParameter(string $name): bool;

            /**
             * @param array<int|string, mixed>|bool|float|int|string|\UnitEnum|null $value
             */
            public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void;
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Sanitizer {

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sindla\Bundle\AuroraBundle\Utils\Sanitizer\Sanitizer;

class SanitizerTest extends TestCase
{
    public function testHtmlMinifyUsesJsMinifier(): void
    {
        $container = new class implements ContainerInterface {
            public function set(string $id, ?object $service): void {}
            public function get(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                return null;
            }
            public function has(string $id): bool { return false; }
            public function initialized(string $id): bool { return false; }
            /**
             * @return array<int|string, mixed>|bool|float|int|string|\UnitEnum|null
             */
            public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null { return null; }
            public function hasParameter(string $name): bool { return false; }
            /**
             * @param array<int|string, mixed>|bool|float|int|string|\UnitEnum|null $value
             */
            public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void {}
        };

        $sanitizer = new class($container) extends Sanitizer {
            /**
             * @var list<string>
             */
            public array $calls = [];
            /**
             * @param string $input
             * @return string
             */
            public function minifyCSS($input)
            {
                $this->calls[] = 'css';
                return $input;
            }
            /**
             * @param string $input
             * @param bool   $removeConsoleOutputs
             * @return string
             */
            public function minifyJS($input, $removeConsoleOutputs = false)
            {
                $this->calls[] = 'js';
                return $input;
            }
        };

        $sanitizer->htmlMinify('<script>console.log("x");</script>');

        self::assertContains('js', $sanitizer->calls);
        self::assertNotContains('css', $sanitizer->calls);
    }
}

}
