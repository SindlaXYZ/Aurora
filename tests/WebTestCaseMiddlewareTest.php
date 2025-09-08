<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests;

use PHPUnit\Framework\TestCase;

class WebTestCaseMiddlewareTest extends TestCase
{
    public function testProgressAdvanceUsesParentName(): void
    {
        $code = <<<'CODE'
        require 'vendor/autoload.php';
        class Dummy extends \Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware
        {
            protected function getParentOrNull(): ?string
            {
                return 'ParentName';
            }
            public function run(): void
            {
                $this->progressStart(1);
                $this->progressAdvance();
            }
        }
        (new Dummy())->run();
        CODE;
        $output = shell_exec('php -r '.escapeshellarg($code).' 2>&1');
        $this->assertStringContainsString('Run ParentName() tests', $output);
    }

    public function testProgressAdvanceFallsBackToUnknown(): void
    {
        $code = <<<'CODE'
        require 'vendor/autoload.php';
        class Dummy extends \Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware
        {
            protected function getParentOrNull(): ?string
            {
                return null;
            }
            public function run(): void
            {
                $this->progressStart(1);
                $this->progressAdvance();
            }
        }
        (new Dummy())->run();
        CODE;
        $output = shell_exec('php -r '.escapeshellarg($code).' 2>&1');
        $this->assertStringContainsString('Run Unknown() tests', $output);
    }
}
