<?php
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection {
    if (!class_exists(Container::class)) {
        class Container
        {
            /**
             * @param array<string, mixed> $parameters
             */
            public function __construct(
                private array $parameters = []
            ) {
            }

            public function getParameter(string $name): mixed
            {
                if (!array_key_exists($name, $this->parameters)) {
                    throw new \InvalidArgumentException(sprintf('Parameter "%s" not found.', $name));
                }

                return $this->parameters[$name];
            }
        }
    }
}

namespace Symfony\Component\Yaml {
    if (!class_exists(Yaml::class)) {
        class Yaml
        {
            /**
             * @return array<string, mixed>
             */
            public static function parse(string $content): mixed
            {
                return [
                    'aurora' => [
                        'tmp'       => 'var/tmp',
                        'resources' => 'resources',
                        'pwa'       => [
                            'icons' => 'public/icons',
                        ],
                        'other'     => 'value',
                    ],
                ];
            }
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Params {
    use PHPUnit\Framework\TestCase;
    use Sindla\Bundle\AuroraBundle\Utils\Params\Params;
    use Symfony\Component\DependencyInjection\Container;

    class ParamsTest extends TestCase
    {
        public function testConstructorAcceptsSymfonyContainer(): void
        {
            $projectDir = sys_get_temp_dir() . '/aurora_' . uniqid('', true);
            $configDir  = $projectDir . '/config/packages';

            if (!is_dir($configDir)) {
                mkdir($configDir, 0777, true);
            }

            file_put_contents($configDir . '/aurora.yaml', "aurora: {}\n");

            $container = new Container([
                'kernel.project_dir' => $projectDir,
            ]);

            $params   = new Params($container);
            $expected = [
                'tmp'       => $projectDir . '/var/tmp',
                'resources' => $projectDir . '/resources',
                'pwa'       => [
                    'icons' => $projectDir . '/public/icons',
                ],
                'other'     => 'value',
            ];

            $this->assertSame($expected, $params->getAll());
        }
    }
}
