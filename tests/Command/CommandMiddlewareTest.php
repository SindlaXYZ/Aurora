<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;

class CommandMiddlewareTest extends TestCase
{
    public function testProgressBarPreviousDisplayIsDateTime(): void
    {
        $command = new CommandMiddleware();
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('progressBarPreviousDisplay');
        $property->setAccessible(true);

        $this->assertInstanceOf(\DateTimeInterface::class, $property->getValue($command));
    }
}
