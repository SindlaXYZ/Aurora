<?php

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface
        {
            public const EXCEPTION_ON_INVALID_REFERENCE = 1;

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object;

            public function has(string $id): bool;
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
            public function get(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                return null;
            }
            public function has(string $id): bool { return false; }
        };

        $sanitizer = new class($container) extends Sanitizer {
            public array $calls = [];
            public function minifyCSS($input)
            {
                $this->calls[] = 'css';
                return $input;
            }
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
