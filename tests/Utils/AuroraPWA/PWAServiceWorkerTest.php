<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPWA;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
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
        $this->assertStringContainsString('no-store|no-cache|private', $serviceWorker);
        $this->assertStringContainsString('"/aurora/pwa-offline"', $serviceWorker);

        foreach (['(' => ')', '{' => '}', '[' => ']'] as $open => $close) {
            $this->assertSame(substr_count($serviceWorker, $open), substr_count($serviceWorker, $close), sprintf('Unbalanced "%s%s".', $open, $close));
        }
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
