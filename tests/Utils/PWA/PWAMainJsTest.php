<?php
declare(strict_types=1);

namespace Symfony\Component\HttpFoundation {
    if (!class_exists(HeaderBag::class)) {
        class HeaderBag
        {
            private array $headers      = [];
            private array $cacheControl = [];

            public function __construct(array $headers = [])
            {
                foreach ($headers as $key => $value) {
                    $this->set($key, $value);
                }
            }

            public function addCacheControlDirective(string $key, mixed $value): void
            {
                $this->cacheControl[$key] = $value;
            }

            public function set(string $key, mixed $value): void
            {
                $this->headers[strtolower($key)] = $value;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                $lowerKey = strtolower($key);

                return $this->headers[$lowerKey] ?? $default;
            }

            public function getCacheControl(): array
            {
                return $this->cacheControl;
            }

            public function all(): array
            {
                return $this->headers;
            }
        }
    }

    if (!class_exists(Response::class)) {
        class Response
        {
            public HeaderBag $headers;
            private string   $content;
            private int      $status;

            public function __construct(string $content = '', int $status = 200, array $headers = [])
            {
                $this->content = $content;
                $this->status  = $status;
                $this->headers = new HeaderBag($headers);
            }

            public function getContent(): string
            {
                return $this->content;
            }

            public function getStatusCode(): int
            {
                return $this->status;
            }
        }
    }

    if (!class_exists(JsonResponse::class)) {
        class JsonResponse extends Response
        {
            public function __construct(mixed $data = null)
            {
                parent::__construct(json_encode($data, JSON_THROW_ON_ERROR));
            }
        }
    }

    if (!class_exists(BinaryFileResponse::class)) {
        class BinaryFileResponse extends Response
        {
        }
    }

    if (!class_exists(ParameterBag::class)) {
        class ParameterBag
        {
            public function __construct(private array $parameters = [])
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->parameters[$key] ?? $default;
            }
        }
    }

    if (!class_exists(Request::class)) {
        class Request
        {
            public ParameterBag $cookies;

            public function __construct(
                private string $host,
                private string $requestUri,
                array          $cookies = []
            )
            {
                $this->cookies = new ParameterBag($cookies);
            }

            public function getHost(): string
            {
                return $this->host;
            }

            public function getRequestUri(): string
            {
                return $this->requestUri;
            }
        }
    }

    if (!class_exists(RequestStack::class)) {
        class RequestStack
        {
            public function __construct(private \Symfony\Component\HttpFoundation\Session\Session $session)
            {
            }

            public function getSession(): \Symfony\Component\HttpFoundation\Session\Session
            {
                return $this->session;
            }
        }
    }
}

namespace Symfony\Component\HttpFoundation\Session {
    if (!class_exists(Session::class)) {
        class Session
        {
            public function __construct(private array $values = [])
            {
            }

            public function get(string $name, mixed $default = null): mixed
            {
                return $this->values[$name] ?? $default;
            }
        }
    }
}

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface extends \Psr\Container\ContainerInterface
        {
            public function set(string $id, mixed $service): void;

            public function getParameter(string $name): mixed;

            public function hasParameter(string $name): bool;

            public function setParameter(string $name, mixed $value): void;
        }
    }
}

namespace Twig {
    if (!class_exists(Environment::class)) {
        class Environment
        {
            /** @var array<string, mixed>|null */
            public ?array $lastContext = null;

            public function render(string $template, array $context = []): string
            {
                $this->lastContext = $context;

                return '// rendered: ' . $template;
            }
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\PWA {

    use PHPUnit\Framework\TestCase;
    use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;
    use Symfony\Component\DependencyInjection\ContainerInterface;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\RequestStack;
    use Symfony\Component\HttpFoundation\Session\Session;
    use Twig\Environment;

    /**
     * @covers \Sindla\Bundle\AuroraBundle\Utils\PWA\PWA::mainJS
     */
    final class PWAMainJsTest extends TestCase
    {
        public function testTranslationsUseExpectedKeys(): void
        {
            $container = new class implements ContainerInterface {
                /** @var array<string, mixed> */
                private array $parameters
                    = [
                        'aurora.pwa.enabled'              => true,
                        'aurora.pwa.debug'                => false,
                        'aurora.pwa.automatically_prompt' => true,
                        'aurora.pwa.version_append'       => '',
                        'kernel.environment'              => 'dev',
                    ];

                /** @var array<string, mixed> */
                private array $services
                    = [
                        'aurora.git' => null,
                    ];

                public function __construct()
                {
                    $this->services['aurora.git'] = new class {
                        public function getHash(): string
                        {
                            return 'hash-value';
                        }
                    };
                }

                public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
                {
                    if (!$this->has($id)) {
                        throw new \RuntimeException(sprintf('Service %s not found.', $id));
                    }

                    return $this->services[$id];
                }

                public function has(string $id): bool
                {
                    return array_key_exists($id, $this->services);
                }

                public function set(string $id, mixed $service): void
                {
                    $this->services[$id] = $service;
                }

                public function getParameter(string $name): \UnitEnum|array|string|int|float|bool|null
                {
                    return $this->parameters[$name] ?? null;
                }

                public function hasParameter(string $name): bool
                {
                    return array_key_exists($name, $this->parameters);
                }

                public function setParameter(string $name, mixed $value): void
                {
                    $this->parameters[$name] = $value;
                }
            };

            $session      = new Session(['PHPSESSID' => 'session-value']);
            $requestStack = new RequestStack($session);
            $twig         = new Environment();
            $pwa          = new PWA($container, $requestStack, $twig);

            $request = new Request('example.com', '/pwa/main.js', ['PHPSESSID' => 'cookie-session']);

            $response = $pwa->mainJS($request);

            self::assertSame('// rendered: @Aurora/pwa-main.js.twig', $response->getContent());
            self::assertSame(200, $response->getStatusCode());

            self::assertIsArray($twig->lastContext);
            self::assertArrayHasKey('translations', $twig->lastContext);
            $translations = $twig->lastContext['translations'];

            self::assertIsArray($translations);
            self::assertArrayHasKey('notificationInstallTheApp', $translations);
            self::assertArrayNotHasKey('nnotificationInstallTheApp', $translations);
            self::assertSame('Install the App', $translations['notificationInstallTheApp']);
        }
    }
}
