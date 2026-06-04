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

        $this->assertInstanceOf(\DateTimeInterface::class, $property->getValue($command));
    }

    public function testReadYamlFileParsesYaml(): void
    {
        $command = new CommandMiddleware();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('readYamlFile');

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, "foo: bar\n");

        try {
            $result = $method->invoke($command, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testReadYamlFileParsesNestedStructuresWithoutSymfonyYaml(): void
    {
        $command     = new CommandMiddleware();
        $reflection  = new \ReflectionClass($command);
        $method      = $reflection->getMethod('readYamlFile');

        $yaml = <<<YAML
parent:
  child: value
  enabled: true
  count: 5
  price: 12.5
  inline: [first, second]
  numbers: [1, 2, 3]
  list:
    - entry-one
    - entry-two
  nestedList:
    - name: foo
    - name: bar
YAML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'yaml');
        file_put_contents($tmpFile, $yaml);

        try {
            $result = $method->invoke($command, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        $expected = [
            'parent' => [
                'child'      => 'value',
                'enabled'    => true,
                'count'      => 5,
                'price'      => 12.5,
                'inline'     => ['first', 'second'],
                'numbers'    => [1, 2, 3],
                'list'       => ['entry-one', 'entry-two'],
                'nestedList' => [
                    ['name' => 'foo'],
                    ['name' => 'bar'],
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}
