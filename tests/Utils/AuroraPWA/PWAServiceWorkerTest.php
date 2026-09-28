<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPWA;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;

class PWAServiceWorkerTest extends TestCase
{
    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('dataServiceWorkerLists')]
    public function testServiceWorkerListsAreJsonArrays(array $parameters, string $expected): void
    {
        $template = '{{ precache|raw }} {{ prevent_cache|raw }} {{ prevent_cache_header_request_accept|raw }} {{ external_cache|raw }}';

        $response = $this->createPWA($parameters, $template)->serviceWorkerJS(Request::create('/pwa-sw.js'));

        // Empty lists used to render "[//]" (a comment: broken JavaScript) and "['']" (a pattern that matches every URL)
        $this->assertSame($expected, $response->getContent());
    }

    public static function dataServiceWorkerLists(): iterable
    {
        yield 'empty lists' => [
            [],
            '["/?pwa","/aurora/pwa-offline"] [] [] []',
        ];
        yield 'configured lists' => [
            [
                'aurora.pwa.precache'                            => ['/', '/?pwa'],
                'aurora.pwa.prevent_cache'                       => ['/admin', '.*\.mp4', "/it's"],
                'aurora.pwa.prevent_cache_header_request_accept' => ['text/html'],
                'aurora.pwa.external_cache'                      => ['fonts.gstatic.com'],
            ],
            '["/?pwa","/aurora/pwa-offline","/"] ["/admin",".*\\\\.mp4","/it\'s"] ["text/html"] ["fonts.gstatic.com"]',
        ];
    }

    #[DataProvider('dataEnvironments')]
    public function testServiceWorkerLoadsThePagesFromTheNetworkFirst(string $environment): void
    {
        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(dirname(__DIR__, 3) . '/src/templates', 'Aurora');

        $serviceWorker = $this->createPWA(['kernel.environment' => $environment], null, $twig)
            ->serviceWorkerJS(Request::create('/pwa-sw.js'))
            ->getContent();

        // Every page used to be served from the cache first: outdated pages, and the page of a signed-in user shown after the logout
        $this->assertMatchesRegularExpression('/[\'"]navigate[\'"]\s*===\s*event\.request\.mode/', $serviceWorker);
        $this->assertStringContainsString('function isCacheable(', $serviceWorker);
        // The precached pages are stored whatever their Cache-Control: the page of the user signed in when the worker was installed
        // was shown offline after the logout
        $this->assertMatchesRegularExpression('/if\s*\(\s*cachedPage\s*&&\s*mayBeReused\(cachedPage\)\s*\)/', $serviceWorker);
        // ... and returned from the cache first for the other requests (e.g. a page fetched by Turbo), also online
        $this->assertMatchesRegularExpression('/if\s*\(\s*cachedResponse\s*&&\s*mayBeReused\(cachedResponse\)\s*\)/', $serviceWorker);
        $this->assertStringContainsString('no-store|no-cache|private', $serviceWorker);
        $this->assertStringContainsString('"/aurora/pwa-offline"', $serviceWorker);

        // A failing cache write used to discard the network response (the outer catch served a stale page or the offline page)
        $this->assertMatchesRegularExpression(
            '/try\s*\{\s*const cache\s*=\s*await caches\.open\(RUNTIME\);\s*await cache\.put\(event\.request,\s*response\.clone\(\)\);?\s*\}\s*catch\s*\(/',
            $serviceWorker
        );

        foreach (['(' => ')', '{' => '}', '[' => ']'] as $open => $close) {
            $this->assertSame(substr_count($serviceWorker, $open), substr_count($serviceWorker, $close), sprintf('Unbalanced "%s%s".', $open, $close));
        }
    }

    #[DataProvider('dataEnvironments')]
    public function testServiceWorkerAnswersOnlyTheFailedNavigationsWithTheOfflinePage(string $environment): void
    {
        $serviceWorker = $this->createPWA(['kernel.environment' => $environment], null, $this->createBundleTwig())
            ->serviceWorkerJS(Request::create('/pwa-sw.js'))
            ->getContent();

        // A PUT / PATCH / DELETE made offline (only POST was excluded) used to get the offline page, status 200: the application
        // believed the change was saved
        $this->assertMatchesRegularExpression('/if\s*\(\s*[\'"]GET[\'"]\s*!==\s*event\.request\.method\s*\)\s*\{\s*return\s*(false|!1)?;?\s*\}/', $serviceWorker);
        $this->assertStringNotContainsString('POST', $serviceWorker);

        // An image, a script or a fetch() used to get the offline page (HTML, status 200) instead of an error
        $this->assertMatchesRegularExpression('/catch\s*\(\s*err\s*\)\s*\{\s*if\s*\(\s*!isNavigation\s*\)\s*\{?\s*return\s+Response\.error\(\)/', $serviceWorker);
    }

    #[DataProvider('dataEnvironments')]
    public function testMainScriptDoesNotReloadThePageOfAFirstTimeVisitor(string $environment): void
    {
        $mainJs = $this->createPWA(['kernel.environment' => $environment], null, $this->createBundleTwig())
            ->mainJS(Request::create('/pwa-main.js'))
            ->getContent();

        // The first worker takes control of the page (clients.claim()): the page of every first-time visitor used to be reloaded
        $this->assertMatchesRegularExpression('/let\s+hasController\s*=\s*!!navigator\.serviceWorker\.controller/', $mainJs);
        $this->assertMatchesRegularExpression(
            '/[\'"]controllerchange[\'"]\s*,\s*function\s*\(\)\s*\{\s*if\s*\(\s*!hasController\s*\)\s*\{\s*hasController\s*=\s*(true|!0)\s*;?\s*return\s*;?\s*\}/',
            $mainJs
        );
        $this->assertLessThan(strpos($mainJs, 'window.location.reload()'), strpos($mainJs, '!hasController'));
    }

    public static function dataEnvironments(): iterable
    {
        yield 'dev' => ['dev'];
        yield 'prod (minified)' => ['prod'];
    }

    public function testIconIsFoundWhenTheRequestHasAQueryString(): void
    {
        $iconsDirectory = sys_get_temp_dir() . '/aurora-pwa-icons-' . bin2hex(random_bytes(4));
        mkdir($iconsDirectory);
        file_put_contents($iconsDirectory . '/favicon.ico', 'icon');

        try {
            $response = $this->createPWA(['aurora.pwa.icons' => $iconsDirectory], '')->icon(Request::create('/favicon.ico?v=2'));

            // The file used to be looked up with the request URI ("favicon.ico?v=2"): the placeholder icon was returned
            $this->assertInstanceOf(BinaryFileResponse::class, $response);
        } finally {
            @unlink($iconsDirectory . '/favicon.ico');
            @rmdir($iconsDirectory);
        }
    }

    #[DataProvider('dataScripts')]
    public function testADisabledPwaServesNoScript(string $method): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $response = $this->createPWA(['aurora.pwa.enabled' => 'false'], null, $twig)->{$method}(Request::create('/script.js'));

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('text/javascript', $response->headers->get('Content-Type'));
    }

    public static function dataScripts(): iterable
    {
        yield 'main script' => ['mainJS'];
        yield 'service worker' => ['serviceWorkerJS'];
    }

    public function testIconFallsBackToTheAndroidThenToTheAppleIconOfTheRequestedSize(): void
    {
        $iconsDirectory = $this->createIconsDirectory(['android-icon-48x48.png' => 'android', 'apple-icon-57x57.png' => 'apple icon']);

        try {
            $pwa = $this->createPWA(['aurora.pwa.icons' => $iconsDirectory, 'kernel.project_dir' => $iconsDirectory], '');

            $androidIcon = $pwa->icon(Request::create('/favicon-48x48.png'));
            $this->assertInstanceOf(BinaryFileResponse::class, $androidIcon);
            $this->assertSame($iconsDirectory . '/android-icon-48x48.png', $androidIcon->getFile()->getPathname());
            $this->assertSame('7', $androidIcon->headers->get('Content-Length'));

            $appleIcon = $pwa->icon(Request::create('/apple-touch-icon-57x57.png'));
            $this->assertInstanceOf(BinaryFileResponse::class, $appleIcon);
            $this->assertSame($iconsDirectory . '/apple-icon-57x57.png', $appleIcon->getFile()->getPathname());
            $this->assertSame('10', $appleIcon->headers->get('Content-Length'));
        } finally {
            $this->removeIconsDirectory($iconsDirectory);
        }
    }

    #[DataProvider('dataMissingIcons')]
    public function testIconAnswersAPlaceholderIconWhenNoFileMatches(string $path): void
    {
        $iconsDirectory = $this->createIconsDirectory(['android-icon-48x48.png' => 'android']);

        try {
            $pwa = $this->createPWA(['aurora.pwa.icons' => $iconsDirectory, 'kernel.project_dir' => $iconsDirectory], '');

            // The missing icon is also reported with E_USER_NOTICE, which is not asserted: in debug mode the notice is an exception
            $previousHandler = null;
            $previousHandler = set_error_handler(static function (int $errno, string $message, string $file, int $line) use (&$previousHandler): bool {
                return E_USER_NOTICE === $errno || (null !== $previousHandler && (bool)$previousHandler($errno, $message, $file, $line));
            });

            try {
                $response = $pwa->icon(Request::create($path));
            } finally {
                restore_error_handler();
            }

            $this->assertNotInstanceOf(BinaryFileResponse::class, $response);
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
            $this->assertSame('image/x-icon', $response->headers->get('Content-Type'));
            // A 16x16 ICO file
            $this->assertStringStartsWith("\x00\x00\x01\x00\x01\x00\x10\x10", (string)$response->getContent());
        } finally {
            $this->removeIconsDirectory($iconsDirectory);
        }
    }

    public static function dataMissingIcons(): iterable
    {
        yield 'no size in the name' => ['/missing.ico'];
        yield 'a zero size' => ['/icon-0x48.png'];
        yield 'no icon of this size' => ['/icon-72x72.png'];
    }

    /**
     * The "App\Service\AuroraService" hooks of the host application (in a separate process: the class would exist for every other test)
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheHostApplicationHooksCustomizeTheManifestTheMainScriptAndTheVersion(): void
    {
        $hook = new class {
            public ?Request $request = null;

            public function pwaAppName(): string
            {
                return 'App of ' . $this->request?->getHost();
            }

            public function pwaAppShortName(): string
            {
                return 'Short';
            }

            public function pwaDescription(): string
            {
                return 'Hooked description';
            }

            public function pwaThemeColor(): string
            {
                return '#111111';
            }

            public function pwaBackgroundColor(): string
            {
                return '#222222';
            }

            public function transNotificationInstallTheApp(): string
            {
                return "Installer l'application";
            }

            public function transNotificationNewVersion(): string
            {
                return 'Nouvelle version';
            }

            public function transNotificationReload(): string
            {
                return 'Recharger';
            }

            public function pwaVersionAppend(): string
            {
                return 'release-7';
            }
        };
        class_alias($hook::class, 'App\Service\AuroraService');

        // A 1x1 PNG: the size of the maskable icon is read from the image
        $icons = ['android-icon-maskable.png' => (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HwAFgwJ/lXzyNwAAAABJRU5ErkJggg==')];
        foreach ([36, 48, 72, 96, 144, 192, 512] as $size) {
            $icons["android-icon-{$size}x{$size}.png"] = 'png';
        }
        $iconsDirectory = $this->createIconsDirectory($icons);

        try {
            $parameters = [
                'aurora.pwa.icons'            => $iconsDirectory,
                'aurora.pwa.app_name'         => 'Configured name',
                'aurora.pwa.theme_color'      => '#000000',
                'aurora.pwa.background_color' => '#ffffff',
                'kernel.project_dir'          => $iconsDirectory,
            ];

            $mainJsTemplate = '{{ translations.notificationInstallTheApp }}|{{ translations.notificationNewVersion }}|{{ translations.notificationReload }}|{{ pwaVersion }}';
            $twig           = new Environment(new ArrayLoader(['@Aurora/pwa-main.js.twig' => $mainJsTemplate]), ['autoescape' => false]);
            $pwa            = $this->createPWA($parameters, null, $twig);
            $version        = 'hash_' . substr(sha1('release-7'), 0, 15);

            $request  = Request::create('https://hooked.example.com/manifest.json');
            $manifest = json_decode((string)$pwa->manifestJSON($request)->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('App of hooked.example.com', $manifest['name']);
            $this->assertSame('Short', $manifest['short_name']);
            $this->assertSame('Hooked description', $manifest['description']);
            $this->assertSame('#111111', $manifest['theme_color']);
            $this->assertSame('#222222', $manifest['background_color']);

            $this->assertSame("Installer l\\'application|Nouvelle version|Recharger|{$version}", $pwa->mainJS($request)->getContent());
            $this->assertSame($version, $pwa->version($request));

            // A configured suffix wins over the hook
            $parameters['aurora.pwa.version_append'] = 'v2';
            $this->assertSame('hash_v2', $this->createPWA($parameters, '')->version($request));
        } finally {
            $this->removeIconsDirectory($iconsDirectory);
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function createIconsDirectory(array $files): string
    {
        $iconsDirectory = sys_get_temp_dir() . '/aurora-pwa-icons-' . bin2hex(random_bytes(4));
        mkdir($iconsDirectory);

        foreach ($files as $name => $content) {
            file_put_contents($iconsDirectory . '/' . $name, $content);
        }

        return $iconsDirectory;
    }

    private function removeIconsDirectory(string $iconsDirectory): void
    {
        foreach (glob($iconsDirectory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($iconsDirectory);
    }

    private function createBundleTwig(): Environment
    {
        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(dirname(__DIR__, 3) . '/src/templates', 'Aurora');

        return $twig;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createPWA(array $parameters, ?string $serviceWorkerTemplate, ?Environment $twig = null): AuroraPWA
    {
        $parameterBag = new ParameterBag(array_merge([
            'kernel.environment'     => 'dev',
            'kernel.project_dir'     => sys_get_temp_dir(),
            'aurora.pwa.start_url'   => '/?pwa',
            'aurora.pwa.offline'     => '/aurora/pwa-offline',
            'aurora.pwa.precache'    => [],
            'aurora.pwa.prevent_cache' => [],
            'aurora.pwa.external_cache' => [],
        ], $parameters));

        $git = new class extends AuroraGit {
            public function __construct()
            {
            }

            public function getHash(?string $branch = null)
            {
                return 'hash';
            }
        };

        $twig ??= new Environment(new ArrayLoader(['@Aurora/pwa-sw.js.twig' => (string)$serviceWorkerTemplate]));

        return new AuroraPWA($parameterBag, $twig, $git);
    }
}
