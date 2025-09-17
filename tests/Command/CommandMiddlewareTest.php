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

    public function testReadYamlFileParsesYaml(): void
    {
        $command = new CommandMiddleware();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('readYamlFile');
        $method->setAccessible(true);

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, "foo: bar\n");

        $result = $method->invoke($command, $tmpFile);

        $this->assertSame(['foo' => 'bar'], $result);
    }
}
