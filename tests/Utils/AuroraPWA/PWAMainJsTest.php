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

            public function set(string $name, mixed $value): void
            {
                $this->values[$name] = $value;
            }
        }
    }
}

namespace Symfony\Component\DependencyInjection\ParameterBag {
    if (!interface_exists(ParameterBagInterface::class)) {
        interface ParameterBagInterface
        {
            public function get(string $name): mixed;

            public function has(string $name): bool;
        }
    }

    if (!class_exists(ParameterBag::class)) {
        class ParameterBag implements ParameterBagInterface
        {
            /**
             * @param array<string, mixed> $parameters
             */
            public function __construct(private array $parameters = [])
            {
            }

            public function get(string $name): mixed
            {
                return $this->parameters[$name] ?? null;
            }

            public function has(string $name): bool
            {
                return array_key_exists($name, $this->parameters);
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
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPWA {

    use PHPUnit\Framework\TestCase;
    use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
    use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
    use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
    use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\RequestStack;
    use Symfony\Component\HttpFoundation\Session\Session;
    use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
    use Twig\Environment;

    /**
     * @covers \Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA::mainJS
     */
    final class PWAMainJsTest extends TestCase
    {
        /**
         * @return array{0:PWA,1:Request,2:Environment}
         */
        private function createMainJsScenario(array $parameterOverrides = []): array
        {
            $parameters = array_merge([
                'aurora.pwa.enabled'              => true,
                'aurora.pwa.debug'                => false,
                'aurora.pwa.automatically_prompt' => true,
                'aurora.pwa.version_append'       => '',
                'kernel.environment'              => 'dev',
            ], $parameterOverrides);

            $parameterBag = new ParameterBag($parameters);

            $git = new class extends AuroraGit {
                public function __construct()
                {
                }

                public function getHash(?string $branch = null)
                {
                    return 'hash-value';
                }
            };

            if (class_exists(MockArraySessionStorage::class)) {
                $session = new Session(new MockArraySessionStorage());
            } else {
                $session = new Session();
            }

            if (method_exists($session, 'setId')) {
                $session->setId('session-value');
            }

            if (method_exists($session, 'set')) {
                $session->set('PHPSESSID', 'session-value');
            } elseif (method_exists($session, 'replace')) {
                $session->replace(['PHPSESSID' => 'session-value']);
            }

            $serverParameters = ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/pwa/main.js'];

            if (!method_exists(Request::class, 'getHost') || !method_exists(Request::class, 'getRequestUri')) {
                $request = new class (
                    [],
                    [],
                    [],
                    ['PHPSESSID' => 'cookie-session'],
                    [],
                    $serverParameters
                ) extends Request {
                    private string $host;

                    private string $requestUri;

                    public function __construct(
                        array $query = [],
                        array $request = [],
                        array $attributes = [],
                        array $cookies = [],
                        array $files = [],
                        array $server = [],
                        $content = null
                    ) {
                        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

                        $this->host       = (string) ($server['HTTP_HOST'] ?? 'localhost');
                        $this->requestUri = (string) ($server['REQUEST_URI'] ?? '/');
                    }

                    public function getHost(): string
                    {
                        return $this->host;
                    }

                    public function getRequestUri(): string
                    {
                        return $this->requestUri;
                    }
                };
            } else {
                $request = new Request(
                    [],
                    [],
                    [],
                    ['PHPSESSID' => 'cookie-session'],
                    [],
                    $serverParameters
                );
            }

            if (property_exists($request, 'cookies') && is_object($request->cookies)) {
                if (method_exists($request->cookies, 'replace')) {
                    $request->cookies->replace(['PHPSESSID' => 'cookie-session']);
                } elseif (method_exists($request->cookies, 'set')) {
                    $request->cookies->set('PHPSESSID', 'cookie-session');
                }
            }

            if (method_exists($request, 'setSession')) {
                $request->setSession($session);
            }

            try {
                $requestStack = new RequestStack($session);
            } catch (\TypeError) {
                $requestStack = new RequestStack();
            }

            if (method_exists($requestStack, 'push')) {
                $requestStack->push($request);
            }

            if (class_exists(\Twig\Loader\ArrayLoader::class)) {
                $twig = new class () extends Environment {
                    public ?array $lastContext = null;

                    public function __construct()
                    {
                        parent::__construct(new \Twig\Loader\ArrayLoader([
                            '@Aurora/pwa-main.js.twig' => '// rendered: @Aurora/pwa-main.js.twig',
                        ]));
                    }

                    public function render(...$arguments): string
                    {
                        $context            = is_array($arguments[1] ?? null) ? $arguments[1] : [];
                        $this->lastContext = $context;

                        return parent::render(...$arguments);
                    }
                };
            } else {
                $twig = new Environment();
            }

            $pwa = new AuroraPWA($parameterBag, $twig, $git);

            return [$pwa, $request, $twig];
        }

        public function testTranslationsUseExpectedKeys(): void
        {
            [$pwa, $request, $twig] = $this->createMainJsScenario();

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

        public function testAutomaticallyPromptRespectsBooleanStrings(): void
        {
            [$pwa, $request, $twig] = $this->createMainJsScenario([
                'aurora.pwa.automatically_prompt' => 'false',
            ]);

            $pwa->mainJS($request);

            self::assertIsArray($twig->lastContext);
            self::assertArrayHasKey('automatically_prompt', $twig->lastContext);
            self::assertFalse($twig->lastContext['automatically_prompt']);
        }

        /**
         * Regression: the service worker version must NOT contain the session identifier.
         *
         * The scenario deliberately sets a "PHPSESSID" session attribute AND cookie (the exact
         * conditions that used to leak into the version and trigger a false "new version
         * available" prompt on every session change). The version must be the git hash alone.
         *
         * @covers \Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA::version
         */
        public function testVersionIsGitHashAloneAndIgnoresSession(): void
        {
            [$pwa, $request] = $this->createMainJsScenario();

            self::assertSame('hash-value', $pwa->version($request));
        }

        /**
         * @covers \Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA::version
         */
        public function testVersionAppendsStablePlainString(): void
        {
            [$pwa, $request] = $this->createMainJsScenario([
                'aurora.pwa.version_append' => 'build-42',
            ]);

            self::assertSame('hash-value_build-42', $pwa->version($request));
        }

        /**
         * @covers \Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA::version
         */
        public function testVersionSanitizesAppendToken(): void
        {
            [$pwa, $request] = $this->createMainJsScenario([
                'aurora.pwa.version_append' => 'v1.2.3 (build/77)!',
            ]);

            self::assertSame('hash-value_v1.2.3build77', $pwa->version($request));
        }

        /**
         * Regression: the legacy "!php/eval `...`" form is no longer evaluated. A time-based
         * expression like date() must NOT influence the version (which would churn the service
         * worker on every request/hour and falsely prompt for an update).
         *
         * @covers \Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA::version
         */
        public function testVersionIgnoresLegacyPhpEvalAppend(): void
        {
            [$pwa, $request] = $this->createMainJsScenario([
                'aurora.pwa.version_append' => "!php/eval `date('Y-m-d H')`",
            ]);

            self::assertSame('hash-value', $pwa->version($request));
        }
    }
}
