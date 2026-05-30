<?php
declare(strict_types=1);

namespace Symfony\Contracts\Cache {
    if (!interface_exists(ItemInterface::class)) {
        interface ItemInterface
        {
            public function getKey(): string;

            public function get(): mixed;

            public function isHit(): bool;

            public function set(mixed $value): static;

            public function expiresAt(?\DateTimeInterface $expiration): static;

            public function expiresAfter(int|\DateInterval|null $time): static;

            public function tag(string|array $tags): static;

            /**
             * @return array<string, mixed>
             */
            public function getMetadata(): array;
        }
    }
}

namespace Symfony\Component\Cache\Adapter {
    use Symfony\Contracts\Cache\ItemInterface;

    if (!class_exists(ApcuAdapter::class)) {
        class DummyCacheItem implements ItemInterface
        {
            private mixed $value = null;

            private bool $hit = false;

            /** @var array<string, mixed> */
            private array $metadata = [];

            public function __construct(private string $key)
            {
            }

            public function getKey(): string
            {
                return $this->key;
            }

            public function get(): mixed
            {
                return $this->value;
            }

            public function isHit(): bool
            {
                return $this->hit;
            }

            public function set(mixed $value): static
            {
                $this->value = $value;
                $this->hit   = true;

                return $this;
            }

            public function expiresAt(?\DateTimeInterface $expiration): static
            {
                $this->metadata['expires_at'] = $expiration;

                return $this;
            }

            public function expiresAfter(int|\DateInterval|null $time): static
            {
                $this->metadata['expires_after'] = $time;

                return $this;
            }

            public function tag(string|array $tags): static
            {
                $this->metadata['tags'] = (array) $tags;

                return $this;
            }

            public function getMetadata(): array
            {
                return $this->metadata;
            }
        }

        class ApcuAdapter
        {
            public function __construct(private string $namespace = '', private int $defaultLifetime = 0)
            {
            }

            public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
            {
                $item = new DummyCacheItem($key);

                return $callback($item);
            }
        }
    }
}

namespace Symfony\Component\HttpFoundation {
    if (!class_exists(HeaderBag::class)) {
        class HeaderBag
        {
            public function __construct(private array $headers = [])
            {
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
        }
    }

    if (!class_exists(Response::class)) {
        class Response
        {
            public HeaderBag $headers;

            public function __construct(private string $content = '', private int $status = 200, array $headers = [])
            {
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

            public function setEncodingOptions(int $options): self
            {
                return $this;
            }
        }
    }

    if (!class_exists(BinaryFileResponse::class)) {
        class BinaryFileResponse extends Response
        {
            public function __construct(private string $file, int $status = 200, array $headers = [])
            {
                parent::__construct('', $status, $headers);
            }

            public function getFile(): string
            {
                return $this->file;
            }
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

            public function set(string $key, mixed $value): void
            {
                $this->parameters[$key] = $value;
            }

            public function replace(array $parameters = []): void
            {
                $this->parameters = $parameters;
            }
        }
    }

    if (!class_exists(Request::class)) {
        class Request
        {
            public ParameterBag $cookies;

            private string $host = 'localhost';

            private string $requestUri = '/';

            public function __construct(mixed ...$arguments)
            {
                if (is_string($arguments[0] ?? null)) {
                    $this->host       = $arguments[0];
                    $this->requestUri = (string) ($arguments[1] ?? '/');
                    $cookies          = is_array($arguments[2] ?? null) ? $arguments[2] : [];
                } else {
                    $server           = is_array($arguments[5] ?? null) ? $arguments[5] : [];
                    $this->host       = (string) ($server['HTTP_HOST'] ?? 'localhost');
                    $this->requestUri = (string) ($server['REQUEST_URI'] ?? '/');
                    $cookies          = is_array($arguments[3] ?? null) ? $arguments[3] : [];
                }

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

            public function getPathInfo(): string
            {
                return $this->requestUri;
            }
        }
    }

    if (!class_exists(RequestStack::class)) {
        class RequestStack
        {
            private ?\Symfony\Component\HttpFoundation\Request $currentRequest = null;

            public function __construct(private \Symfony\Component\HttpFoundation\Session\Session $session)
            {
            }

            public function getSession(): \Symfony\Component\HttpFoundation\Session\Session
            {
                return $this->session;
            }

            public function push(Request $request): void
            {
                $this->currentRequest = $request;
            }

            public function getCurrentRequest(): ?Request
            {
                return $this->currentRequest;
            }
        }
    }
}

namespace Symfony\Component\HttpFoundation\Session {
    if (!class_exists(Session::class)) {
        class Session
        {
            private array $values = [];

            public function get(string $name, mixed $default = null): mixed
            {
                return $this->values[$name] ?? $default;
            }

            public function set(string $name, mixed $value): void
            {
                $this->values[$name] = $value;
            }
        }
    }
}

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface extends \Psr\Container\ContainerInterface
        {
            public const EXCEPTION_ON_INVALID_REFERENCE = 1;

            public const NULL_ON_INVALID_REFERENCE = 0;

            public const IGNORE_ON_INVALID_REFERENCE = 2;

            public const IGNORE_ON_UNINITIALIZED_REFERENCE = 3;

            public function set(string $id, mixed $service): void;

            public function initialized(string $id): bool;

            public function getParameter(string $name): mixed;

            public function hasParameter(string $name): bool;

            public function setParameter(string $name, mixed $value): void;
        }
    }
}

namespace Symfony\Component\Serializer\Encoder {
    if (!class_exists(XmlEncoder::class)) {
        class XmlEncoder
        {
            /**
             * @param array<string, mixed> $context
             */
            public function encode(array $data, string $format, array $context = []): string
            {
                $root = $context['xml_root_node_name'] ?? 'root';

                return sprintf('<%1$s></%1$s>', $root);
            }
        }
    }
}

namespace Twig {
    if (!class_exists(Environment::class)) {
        class Environment
        {
            /** @var array<string, mixed>|null */
            public ?array $lastContext = null;

            public function render($template, array $context = []): string
            {
                $this->lastContext = $context;

                return '// rendered: ' . $template;
            }

            public function display($template, array $context = []): void
            {
                $this->render($template, $context);
            }
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPWA {

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;

final class PWACacheTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = scandir($directory);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    @unlink($directory . DIRECTORY_SEPARATOR . $file);
                }
            }

            @rmdir($directory);
        }

        $this->tempDirectories = [];

        parent::tearDown();
    }

    public function testManifestJsonReturnsConfiguredValues(): void
    {
        $iconsDirectory = $this->createIconsDirectory();

        $container = $this->createContainer([
            'aurora.pwa.app_name'        => 'Test App',
            'aurora.pwa.app_short_name'  => 'Test',
            'aurora.pwa.app_description' => 'Just a test manifest',
            'aurora.pwa.start_url'       => '/start',
            'aurora.pwa.display'         => 'fullscreen',
            'aurora.pwa.icons'           => $iconsDirectory,
        ]);

        $session      = $this->createSession();
        $request      = $this->createRequest(['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/manifest.json']);
        $requestStack = $this->createRequestStack($session, $request);
        $twig         = $this->createTwigEnvironment();

        $pwa      = new AuroraPWA($container, $requestStack, $twig);
        $response = $pwa->manifestJSON($request);

        self::assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Test App', $data['name']);
        self::assertSame('Test', $data['short_name']);
        self::assertSame('/start', $data['start_url']);
    }

    public function testBrowserConfigReturnsXmlResponse(): void
    {
        $iconsDirectory = $this->createIconsDirectory();

        $container = $this->createContainer([
            'aurora.pwa.icons' => $iconsDirectory,
        ]);

        $session      = $this->createSession();
        $request      = $this->createRequest(['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/browserconfig.xml']);
        $requestStack = $this->createRequestStack($session, $request);
        $twig         = $this->createTwigEnvironment();

        $pwa      = new AuroraPWA($container, $requestStack, $twig);
        $response = $pwa->browserConfig($request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<browserconfig>', $response->getContent());
    }

    public function testIconReturnsBinaryFileResponse(): void
    {
        $iconsDirectory = $this->createIconsDirectory();

        $container = $this->createContainer([
            'aurora.pwa.icons' => $iconsDirectory,
        ]);

        $session      = $this->createSession();
        $request      = $this->createRequest(['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/android-icon-36x36.png']);
        $requestStack = $this->createRequestStack($session, $request);
        $twig         = $this->createTwigEnvironment();

        $pwa      = new AuroraPWA($container, $requestStack, $twig);
        $response = $pwa->icon($request);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
    }

    private function createIconsDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aurora-pwa-' . uniqid('', true);
        mkdir($directory);

        foreach ([36, 48, 72, 96, 144, 192, 512] as $size) {
            file_put_contents($directory . DIRECTORY_SEPARATOR . "android-icon-{$size}x{$size}.png", '');
        }

        $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HwAFgwJ/lXzyNwAAAABJRU5ErkJggg==', true);
        if (false === $minimalPng) {
            self::fail('Unable to decode the minimal PNG used for testing.');
        }

        file_put_contents($directory . DIRECTORY_SEPARATOR . 'android-icon-maskable.png', $minimalPng);

        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function createContainer(array $overrides = []): ContainerInterface
    {
        $defaults = [
            'aurora.pwa.app_name'                        => 'Default App',
            'aurora.pwa.app_short_name'                  => 'Default',
            'aurora.pwa.app_description'                 => 'Default description',
            'aurora.pwa.theme_color'                     => '#000000',
            'aurora.pwa.background_color'                => '#ffffff',
            'aurora.pwa.start_url'                       => '/',
            'aurora.pwa.display'                         => 'fullscreen',
            'aurora.pwa.icons'                           => $this->createIconsDirectory(),
            'aurora.pwa.enabled'                         => true,
            'aurora.pwa.debug'                           => false,
            'aurora.pwa.automatically_prompt'            => true,
            'aurora.pwa.version_append'                  => '',
            'aurora.pwa.offline'                         => '/offline',
            'aurora.pwa.precache'                        => [],
            'aurora.pwa.prevent_cache'                   => [],
            'aurora.pwa.prevent_cache_header_request_accept' => [],
            'aurora.pwa.external_cache'                  => [],
            'kernel.environment'                         => 'dev',
        ];

        $parameters = array_merge($defaults, $overrides);

        return new class ($parameters) implements ContainerInterface {
            /** @var array<string, mixed> */
            private array $services = [];

            /**
             * @param array<string, mixed> $parameters
             */
            public function __construct(private array $parameters)
            {
                $this->services['aurora.git'] = new class {
                    public function getHash(): string
                    {
                        return 'hash-value';
                    }
                };

                $this->services['aurora.pwa'] = new class {
                    public function version(Request $request): string
                    {
                        return 'version';
                    }
                };

                $this->services['aurora.helper'] = new class {
                    public function isTrue(mixed $value): bool
                    {
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
                    }

                    public function isFalse(mixed $value): bool
                    {
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === false;
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

            public function initialized(string $id): bool
            {
                return $this->has($id);
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
    }

    private function createSession(): Session
    {
        return new Session();
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     */
    private function createRequest(array $server = [], array $cookies = []): Request
    {
        return new Request([], [], [], $cookies, [], $server);
    }

    private function createRequestStack(Session $session, Request $request): RequestStack
    {
        try {
            $requestStack = new RequestStack($session);
        } catch (\TypeError) {
            $requestStack = new RequestStack();
        }

        if (method_exists($request, 'setSession')) {
            $request->setSession($session);
        }

        if (method_exists($requestStack, 'push')) {
            $requestStack->push($request);
        }

        return $requestStack;
    }

    private function createTwigEnvironment(): Environment
    {
        if (class_exists(\Twig\Loader\ArrayLoader::class)) {
            return new Environment(new \Twig\Loader\ArrayLoader([
                '@Aurora/pwa.html.twig'        => '<html></html>',
                '@Aurora/manifest.html.twig'   => '{}',
                '@Aurora/pwa-main.js.twig'     => 'console.log(1);',
                '@Aurora/pwa.delete.html.twig' => '<html></html>',
                '@Aurora/pwa.unregister.html.twig' => '<html></html>',
            ]));
        }

        return new Environment();
    }
}
}
