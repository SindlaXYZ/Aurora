<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraTwig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper as AuroraHelperUtils;
use Sindla\Bundle\AuroraBundle\Utils\AuroraSanitizer\AuroraSanitizer;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraTwig/UtilityExtensionTest.php --no-coverage
 */
class UtilityExtensionTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->temporaryDirectories = [];
    }

    #[DataProvider('dataFilterAge')]
    public function testFilterAge(\DateTimeInterface $given, int $expected): void
    {
        $extension = new UtilityExtension(
            new Container(),
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $this->assertSame($expected, $extension->filterAge($given));
    }

    public static function dataFilterAge(): array
    {
        $reference = new \DateTime();

        $years = [];
        for ($i = 0; $i < 48; $i++) {
            $years[] = 1900 + (int) round($i * (2025 - 1900) / 47);
        }

        $data  = [];
        $index = 0;
        foreach (range(1, 12) as $month) {
            foreach ([1, 10, 20, 28] as $day) {
                $year = $years[$index++];
                $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $date       = new \DateTime($dateString);
                $expected   = $reference->diff($date)->y;

                $data[] = [$date, $expected];
                $data[] = [new \DateTimeImmutable($dateString), $expected];
            }
        }

        foreach (['2000-02-29', '2024-02-29'] as $extra) {
            $date     = new \DateTime($extra);
            $expected = $reference->diff($date)->y;

            $data[] = [$date, $expected];
            $data[] = [new \DateTimeImmutable($extra), $expected];
        }

        return $data;
    }

    #[DataProvider('dataGetBuild')]
    public function testGetBuild(?int $limit, string $expected): void
    {
        $extension = $this->createUtilityExtensionWithGitHash('abcdef');

        $this->assertSame($expected, $extension->getBuild($limit));
    }

    public static function dataGetBuild(): array
    {
        return [
            'null limit returns full hash'      => [null, 'abcdef'],
            'zero limit returns empty string'   => [0, ''],
            'positive limit truncates hash'     => [3, 'abc'],
            'longer limit keeps full hash'      => [10, 'abcdef'],
            'negative limit returns empty hash' => [-5, ''],
        ];
    }

    #[DataProvider('dataGetHash')]
    public function testGetHash(int $size, int $expectedLength): void
    {
        $extension = new UtilityExtension(
            new Container(),
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $hash = $extension->getHash($size);

        $this->assertSame($expectedLength, strlen($hash));
    }

    public static function dataGetHash(): array
    {
        return [
            [5, 5],
            [50, 40],
            [0, 0],
            [-5, 0],
        ];
    }

    public function testCompressJsOutputsSingleNonce(): void
    {
        $container = new Container();
        $container->setParameter('kernel.environment', 'prod');
        $container->set('aurora.git', new class {
            public function getHash(): string
            {
                return 'hash';
            }
        });

        $extension = new UtilityExtension(
            $container,
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $request = new \Symfony\Component\HttpFoundation\Request();

        ob_start();
        $extension->compressJs($request, false, false, 'file.js');
        $output = ob_get_clean();

        $this->assertSame(1, substr_count($output, 'nonce='));
    }

    public function testIpAndIp2CountryWithoutTheAuroraClientService(): void
    {
        $originalServer = $_SERVER;
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        $container = new Container();
        // No GeoLite2 database in this directory: the country lookup is disabled (null)
        $container->setParameter('aurora.resources', sys_get_temp_dir() . '/aurora-utility-extension-test-' . bin2hex(random_bytes(4)));

        $extension = new UtilityExtension($container, new RequestStack(), $this->createStub(Environment::class), new AuroraHelperUtils());
        $request   = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.8']);

        try {
            // Used to call AuroraClient::ip() (moved to AuroraIP::ip()) on the "aurora.client" service (no longer registered)
            $this->assertSame('198.51.100.8', $extension->ip($request));
            $this->assertNull($extension->ip2Country($request));
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public function testTheNonceIsStableWithinARequestAndRenewedByReset(): void
    {
        $extension = new UtilityExtension(new Container(), new RequestStack(), $this->createStub(Environment::class), new AuroraHelperUtils());

        $nonce = $extension->getNonce();
        $this->assertSame($nonce, $extension->getNonce());

        // "kernel.reset" between two requests of a long-running worker: the same CSP nonce used to be sent to every request
        $extension->reset();
        $this->assertNotSame($nonce, $extension->getNonce());
    }

    public function testPwaWorksWithTheDocumentedSingleArgumentAndWithoutTheOptionalParameters(): void
    {
        $request   = Request::create('https://localhost/');
        $container = new Container();
        $container->set('aurora.git', new class {
            public function getHash(): string
            {
                return 'hash';
            }
        });
        $container->set('aurora.pwa', new class {
            public function version(Request $request): string
            {
                return 'version';
            }
        });

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('display')
            ->with('@Aurora/pwa.html.twig', $this->callback(static fn(array $context): bool => false === $context['pwaDebug']
                && false === $context['debug']
                && null === $context['theme_color']
                && 'version' === $context['pwaVersion']));

        // README: "{{ aurora.pwa(app.request) }}" used to be an ArgumentCountError ($debug was required), and the "aurora.pwa.debug"
        // parameter (not in the reference configuration) a ParameterNotFoundException ("getParameter() ?? false" does not catch it)
        new UtilityExtension($container, new RequestStack(), $twig, new AuroraHelperUtils())->pwa($request);
    }

    public function testGetFiltersMapsTheNamesToTheExtensionMethods(): void
    {
        $extension = $this->createExtension(new Container());

        $filters = [];
        foreach ($extension->getFilters() as $filter) {
            $this->assertInstanceOf(TwigFilter::class, $filter);
            $filters[$filter->getName()] = $filter->getCallable();
        }

        $this->assertSame(['age' => [$extension, 'filterAge'], 'replace_array' => [$extension, 'filterReplaceArray']], $filters);
    }

    public function testTheFiltersAndFunctionsCanBeUsedInATemplate(): void
    {
        $twig = new Environment(new ArrayLoader([
            'template' => "{{ 'a-b_c'|replace_array(['-', '_'], ' ') }}|{{ sha1('abc') }}|{{ isTrue('yes') ? 'yes' : 'no' }}|{{ build(3) }}",
        ]));
        $twig->addExtension($this->createExtension($this->createContainerWithGit('abcdef')));

        $this->assertSame('a b c|a9993e364706816aba3e25717850c26c9cd0d89d|yes|abc', $twig->render('template'));
    }

    public function testGetFunctionsMapsTheNamesToTheirCallables(): void
    {
        $helper    = new AuroraHelperUtils();
        $extension = new UtilityExtension(new Container(), new RequestStack(), $this->createStub(Environment::class), $helper);

        $functions = [];
        foreach ($extension->getFunctions() as $function) {
            $this->assertInstanceOf(TwigFunction::class, $function);
            $functions[$function->getName()] = $function->getCallable();
        }

        $this->assertSame(
            [
                'build'              => [$extension, 'getBuild'],
                'buildDate'          => [$extension, 'getBuildDate'],
                'gitLatestTag'       => [$extension, 'getGitLatestTag'],
                'gitLatestTagHash'   => [$extension, 'getGitLatestTagHash'],
                'hash'               => [$extension, 'getHash'],
                'isTrue'             => [$helper, 'isTrue'],
                'isFalse'            => [$helper, 'isFalse'],
                'sha1'               => [$extension, 'getSha1'],
                'ip2Country'         => [$extension, 'ip2Country'],
                'ip2County'          => [$extension, 'ip2County'],
                'ip2City'            => [$extension, 'ip2City'],
                'compressCss'        => [$extension, 'compressCss'],
                'compressJs'         => [$extension, 'compressJs'],
                'compressCSSJS'      => [$extension, 'compressCSSJS'],
                'manifest'           => [$extension, 'manifest'],
                'pwa'                => [$extension, 'pwa'],
                'pwa.version'        => [$extension, 'pwaVersion'],
                'pwa.delete'         => [$extension, 'pwaDelete'],
                'pwa.unregister'     => [$extension, 'pwaUnregister'],
                'dnsPrefetch'        => [$extension, 'dnsPrefetch'],
                'linkRelDnsPrefetch' => [$extension, 'linkRelDnsPrefetch'],
                'linkRelPreload'     => [$extension, 'linkRelPreload'],
                'nonce'              => [$extension, 'getNonce'],
            ],
            $functions
        );
    }

    public function testGetSha1(): void
    {
        $extension = $this->createExtension(new Container());

        $this->assertSame('da39a3ee5e6b4b0d3255bfef95601890afd80709', $extension->getSha1(''));
        // Twig may pass a number
        $this->assertSame('40bd001563085fc35165329ea1ff5c5ecbdbbeef', $extension->getSha1(123));
    }

    #[DataProvider('dataPwaAvailability')]
    public function testManifestDisplaysTheManifestTemplate(string $url, bool $pwa): void
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('aurora.pwa.theme_color', '#123456');

        $context = null;
        $twig    = $this->createDisplayingTwig('@Aurora/manifest.html.twig', $context);

        $this->assertNull(new UtilityExtension($container, new RequestStack(), $twig, new AuroraHelperUtils())->manifest(Request::create($url), true));
        $this->assertSame(
            ['host' => parse_url($url, PHP_URL_HOST), 'pwa' => $pwa, 'theme_color' => '#123456', 'build' => 'abcdef', 'debug' => true],
            $context
        );
    }

    /**
     * A service worker requires a secure context: HTTPS or localhost
     */
    public static function dataPwaAvailability(): iterable
    {
        yield 'HTTPS' => ['https://example.com/', true];
        yield 'HTTP' => ['http://example.com/', false];
        yield 'localhost' => ['http://localhost/', true];
        yield 'a localhost subdomain' => ['http://app.localhost/', true];
        yield 'a domain starting with localhost' => ['http://localhost.example.com/', false];
    }

    #[DataProvider('dataPwaScripts')]
    public function testPwaDeleteAndPwaUnregisterDisplayTheirTemplates(string $method, string $template): void
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('aurora.pwa.debug', 'true');
        $container->set('aurora.pwa', new class {
            public function version(Request $request): string
            {
                return 'version-of-' . $request->getHost();
            }
        });

        $context = null;
        $twig    = $this->createDisplayingTwig($template, $context);

        new UtilityExtension($container, new RequestStack(), $twig, new AuroraHelperUtils())->{$method}(Request::create('http://example.com/'));

        $this->assertSame(
            ['pwaDebug' => true, 'host' => 'example.com', 'pwa' => false, 'build' => 'abcdef', 'pwaVersion' => 'version-of-example.com', 'debug' => false],
            $context
        );
    }

    public static function dataPwaScripts(): iterable
    {
        yield 'delete the caches' => ['pwaDelete', '@Aurora/pwa.delete.html.twig'];
        yield 'unregister the service worker' => ['pwaUnregister', '@Aurora/pwa.unregister.html.twig'];
    }

    public function testLinkRelDnsPrefetchOutputsOneLinkPerDomain(): void
    {
        $container = new Container();
        $container->setParameter('aurora.dns_prefetch', ['fonts.example.com', 'cdn.example.com']);

        $this->expectOutputString("<link rel='dns-prefetch' href='//fonts.example.com' /><link rel='dns-prefetch' href='//cdn.example.com' />");

        $this->createExtension($container)->linkRelDnsPrefetch();
    }

    public function testDnsPrefetchIsADeprecatedAliasOfLinkRelDnsPrefetch(): void
    {
        $container = new Container();
        $container->setParameter('aurora.dns_prefetch', ['cdn.example.com']);

        $this->expectUserDeprecationMessage('Since sindla/aurora 8.0: The aurora.dnsPrefetch() method is deprecated, use aurora.linkRelDnsPrefetch() instead.');
        $this->expectOutputString("<link rel='dns-prefetch' href='//cdn.example.com' />");

        $this->createExtension($container)->dnsPrefetch();
    }

    public function testLinkRelPreloadOutputsOneLinkPerAsset(): void
    {
        $this->expectOutputString(
            "<link rel='preload' href='/fonts/aurora.woff2' as='font' type='font/woff2' crossorigin />"
            . "<link rel='preload' href='/css/main.css' as='style' type='text/css'  />"
            . "<link rel='preload' href='/js/app.js' as='script' type='text/javascript'  />"
        );

        $this->createExtension(new Container())->linkRelPreload([
            ['asset' => '/fonts/aurora.woff2', 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => true],
            ['asset' => '/css/main.css', 'as' => 'style', 'type' => 'text/css', 'crossorigin' => false],
            ['asset' => '/js/app.js', 'as' => 'script', 'type' => 'text/javascript'],
        ]);
    }

    public function testTheGitFunctionsReadTheRepositoryOfTheProject(): void
    {
        $root = $this->createTemporaryDirectory();
        mkdir($root . '/.git/refs/tags', 0777, true);
        mkdir($root . '/.git/logs/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/logs/refs/heads/main', implode("\n", [
            str_repeat('0', 40) . ' ' . str_repeat('1', 40) . " Aurora Tests <aurora-tests@example.com> 1699990000 +0000\tcommit (initial): First",
            str_repeat('1', 40) . ' ' . str_repeat('2', 40) . " Aurora Tests <aurora-tests@example.com> 1700000000 +0000\tcommit: Second",
            '',
        ]));

        // Natural order: v1.10.0 is the latest tag
        foreach (['v1.2.0' => 'a', 'v1.10.0' => 'b', 'v1.9.3' => 'c'] as $tag => $character) {
            file_put_contents($root . '/.git/refs/tags/' . $tag, str_repeat($character, 40) . "\n");
        }

        $container = new Container();
        $container->set('aurora.git', new AuroraGit(new ParameterBag(['kernel.environment' => 'test', 'aurora.root' => $root])));

        $extension = $this->createExtension($container);

        $this->assertSame(date('Y-m-d H:i:s', 1700000000), $extension->getBuildDate());
        $this->assertSame('v1.10.0', $extension->getGitLatestTag());
        $this->assertSame(str_repeat('b', 40), $extension->getGitLatestTagHash());
    }

    public function testIp2CountyAndIp2CityLookUpTheClientIpWithTheAuroraClientService(): void
    {
        $client = $this->createMock(AuroraClient::class);
        $client->expects($this->once())->method('ip2CityCounty')->with('198.51.100.8')->willReturn('Example County');
        $client->expects($this->once())->method('ip2CityName')->with('198.51.100.8')->willReturn('Example City');

        $container = new Container();
        $container->set('aurora.client', $client);

        $extension = $this->createExtension($container);
        $request   = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.8']);

        $this->assertSame('Example County', $extension->ip2County($request));
        $this->assertSame('Example City', $extension->ip2City($request));
    }

    public function testIp2CountyAndIp2CityWithoutTheAuroraClientServiceAndTheGeoLite2Database(): void
    {
        $container = new Container();
        $container->setParameter('aurora.resources', $this->createTemporaryDirectory());

        $extension = $this->createExtension($container);
        $request   = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.8']);

        $this->assertNull($extension->ip2County($request));
        $this->assertNull($extension->ip2City($request));
    }

    public function testCompressCssLinksTheStylesheetsWithTheBuildVersion(): void
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'prod');

        $this->expectUserDeprecationMessage(sprintf('Since sindla/aurora 8.0: The %s::compressCss() method is deprecated, use compressCSSJS() instead.', UtilityExtension::class));
        $this->expectOutputString(
            "\n\t" . '<link type="text/css" rel="stylesheet" href="css/main.css?v=abcdef" />'
            . "\n\t" . '<link type="text/css" rel="stylesheet" href="https://cdn.example.com/theme.css" />'
        );

        // An asset of 3 characters or less is ignored
        $this->createExtension($container)->compressCss(new Request(), false, false, ' css/main.css ', 'https://cdn.example.com/theme.css', 'a.c');
    }

    public function testCompressCssUsesAUniqueVersionOnDev(): void
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'dev');

        ob_start();
        $this->createExtension($container)->compressCss(new Request(), false, false, 'css/main.css');
        $output = (string)ob_get_clean();

        $this->assertMatchesRegularExpression('#^\n\t<link type="text/css" rel="stylesheet" href="css/main\.css\?v=[0-9a-f]{13}" />$#', $output);
    }

    public function testCompressJsUsesAUniqueVersionOnDev(): void
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'dev');

        $extension = $this->createExtension($container);

        ob_start();
        $extension->compressJs(new Request(), false, false, 'js/app.js');
        $output = (string)ob_get_clean();

        $this->assertMatchesRegularExpression(
            sprintf('#^\n\t<script src="js/app\.js\?v=[0-9a-f]{13}" nonce="%s"></script>$#', preg_quote($extension->getNonce(), '#')),
            $output
        );
    }

    public function testCompressJsCombinesAndMinifiesTheScriptsIntoOneFile(): void
    {
        $root      = $this->createProjectWithAssets(['js/app.js' => "var a = 1;\n", 'js/lib.js' => "function f ( x ) {\n    return x; // id\n}\n"]);
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('aurora.root', $root);
        $container->setParameter('aurora.tmp', $root . '/var/tmp');

        $extension = $this->createExtension($container);

        ob_start();
        $extension->compressJs(new Request(), true, true, 'js/app.js', 'js/lib.js');
        $output = (string)ob_get_clean();

        // The compiled directory is created on dev
        $compiledFile = sprintf('%s/public/static/compiled/%s.js', $root, sha1('abcdef'));
        $this->assertFileExists($compiledFile);
        $this->assertMatchesRegularExpression('#^/\*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\*/var a=1;;function f\(x\)\{return x;\};$#', (string)file_get_contents($compiledFile));
        $this->assertSame(sprintf("\n\t" . '<script src="/static/compiled/%s.js" nonce="%s"></script>', sha1('abcdef'), $extension->getNonce()), $output);
    }

    public function testCompressJsMinifiesEveryScriptIntoItsOwnFileAndKeepsTheCompiledFilesOnProd(): void
    {
        $root = $this->createProjectWithAssets(['js/app.js' => "var a = 1;\n", 'js/lib.js' => "var b = 2;\n"]);
        mkdir($root . '/public/static/compiled', 0777, true);

        $appFile = sprintf('%s/public/static/compiled/%s.js', $root, sha1('js/app.js' . 'abcdef'));
        $libFile = sprintf('%s/public/static/compiled/%s.js', $root, sha1('js/lib.js' . 'abcdef'));
        // Already compiled for this build
        file_put_contents($libFile, '/* compiled */');

        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('aurora.root', $root);
        $container->setParameter('aurora.tmp', $root . '/var/tmp');

        $extension = $this->createExtension($container);

        ob_start();
        $extension->compressJs(new Request(), false, true, 'js/app.js', 'js/lib.js');
        $output = (string)ob_get_clean();

        $this->assertMatchesRegularExpression('#^/\*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\*/var a=1;$#', (string)file_get_contents($appFile));
        $this->assertSame('/* compiled */', file_get_contents($libFile));
        $this->assertSame(
            sprintf("\n\t" . '<script src="/static/compiled/%s" nonce="%s"></script>', basename($appFile), $extension->getNonce())
            . sprintf("\n\t" . '<script src="/static/compiled/%s" nonce="%s"></script>', basename($libFile), $extension->getNonce()),
            $output
        );
    }

    public function testCompressJsFailsWhenTheCompiledDirectoryCannotBeCreated(): void
    {
        $root = $this->createTemporaryDirectory();
        // A file where the "public" directory is expected
        file_put_contents($root . '/public', '');

        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', 'dev');
        $container->setParameter('aurora.root', $root);
        $container->setParameter('aurora.tmp', $root . '/var/tmp');

        $warnings        = [];
        $previousHandler = null;
        $previousHandler = set_error_handler(static function (int $errno, string $message, string $file, int $line) use (&$warnings, &$previousHandler): bool {
            if (E_WARNING === $errno) {
                $warnings[] = $message;

                return true;
            }

            return null !== $previousHandler && (bool)$previousHandler($errno, $message, $file, $line);
        });

        try {
            $this->createExtension($container)->compressJs(new Request(), true, true, 'js/app.js');
            $this->fail('A RuntimeException was expected.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(sprintf('[AURORA] Cannot create cache dir "%s/public/static/compiled".', $root), $exception->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertStringStartsWith('mkdir(', $warnings[0]);
    }

    public function testCompressCssJsLinksTheStylesheetsWithTheBuildVersion(): void
    {
        $container = $this->createContainerForCompressCssJs('prod', $this->createTemporaryDirectory());

        $this->expectOutputString(
            "\n\t" . '<link type="text/css" rel="stylesheet" href="static/css/main.css?v=abcdef" />'
            . "\n\t" . '<link type="text/css" rel="stylesheet" href="https://cdn.example.com/theme.css" />'
        );

        // An asset of 3 characters or less is ignored
        $this->createExtension($container)->compressCSSJS(new Request(), 'css', false, 'static/css/main.css', 'https://cdn.example.com/theme.css', 'a.c');
    }

    public function testCompressCssJsLinksTheScriptsWithAUniqueVersionOnDev(): void
    {
        $extension = $this->createExtension($this->createContainerForCompressCssJs('dev', $this->createTemporaryDirectory()));

        ob_start();
        $extension->compressCSSJS(new Request(), 'js', false, 'static/js/app.js');
        $output = (string)ob_get_clean();

        $this->assertMatchesRegularExpression(
            sprintf('#^\n\t<script src="static/js/app\.js\?v=[0-9a-f]{13}" nonce="%s"></script>$#', preg_quote($extension->getNonce(), '#')),
            $output
        );
    }

    public function testCompressCssJsCombinesAndMinifiesTheStylesheetsIntoOneFile(): void
    {
        $root = $this->createProjectWithAssets(['static/css/main.css' => 'a { color: red; }', 'static/css/print.css' => 'b { color: blue; }']);
        mkdir($root . '/public/static/compiled', 0777, true);

        $assets   = ['static/css/main.css', 'https://cdn.example.com/theme.css', 'static/css/print.css'];
        $fileName = sha1(json_encode($assets) . 'abcdef') . '.css';

        $this->expectOutputString("\n\t" . '<link type="text/css" rel="stylesheet" href="/static/compiled/' . $fileName . '" />');

        $this->createExtension($this->createContainerForCompressCssJs('dev', $root))->compressCSSJS(new Request(), 'css', true, ...$assets);

        // The external stylesheets are imported first, the local ones are minified by the "aurora.sanitizer" service (relative to their path)
        $this->assertMatchesRegularExpression(
            '#^/\*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\*/@import url\("https://cdn\.example\.com/theme\.css"\);'
            . '\[a \{ color: red; \}\|static/css/main\.css\]\[b \{ color: blue; \}\|static/css/print\.css\]$#',
            (string)file_get_contents($root . '/public/static/compiled/' . $fileName)
        );
    }

    public function testCompressCssJsCombinesAndMinifiesTheScriptsIntoOneFile(): void
    {
        $root = $this->createProjectWithAssets(['static/js/app.js' => "var a = 1;\nfunction f ( x ) { return x || 0; }\n"]);
        mkdir($root . '/public/static/compiled', 0777, true);

        $assets    = ['static/js/app.js', 'https://cdn.example.com/lib.js'];
        $fileName  = sha1(json_encode($assets) . 'abcdef') . '.js';
        $extension = $this->createExtension($this->createContainerForCompressCssJs('prod', $root));

        ob_start();
        $extension->compressCSSJS(new Request(), 'js', true, ...$assets);
        $output = (string)ob_get_clean();

        $this->assertSame(sprintf("\n\t" . '<script src="/static/compiled/%s" nonce="%s"></script>', $fileName, $extension->getNonce()), $output);
        // The external scripts are loaded by a script element added to the document head
        $this->assertMatchesRegularExpression(
            '#^/\*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\*/var a=1;function f\(x\)\{return x\|\|0;\};'
            . "var (newScript[0-9a-f]{6}) = document\.createElement\('script'\); \\1\.type = 'text/javascript'; "
            . "\\1\.src = 'https://cdn\.example\.com/lib\.js'; document\.getElementsByTagName\('head'\)\[0\]\.appendChild\(\\1\);$#",
            (string)file_get_contents($root . '/public/static/compiled/' . $fileName)
        );
    }

    public function testCompressCssJsKeepsAnExistingCompiledFileOnProd(): void
    {
        $root = $this->createProjectWithAssets(['static/css/main.css' => 'a { color: red; }']);
        mkdir($root . '/public/static/compiled', 0777, true);

        $fileName = sha1(json_encode(['static/css/main.css']) . 'abcdef') . '.css';
        file_put_contents($root . '/public/static/compiled/' . $fileName, '/* compiled */');

        $this->expectOutputString("\n\t" . '<link type="text/css" rel="stylesheet" href="/static/compiled/' . $fileName . '" />');

        $this->createExtension($this->createContainerForCompressCssJs('prod', $root))->compressCSSJS(new Request(), 'css', true, 'static/css/main.css');

        $this->assertSame('/* compiled */', file_get_contents($root . '/public/static/compiled/' . $fileName));
    }

    private function createExtension(Container $container): UtilityExtension
    {
        return new UtilityExtension($container, new RequestStack(), $this->createStub(Environment::class), new AuroraHelperUtils());
    }

    private function createContainerWithGit(string $hash): Container
    {
        $container = new Container();
        $container->set('aurora.git', new class($hash) {
            public function __construct(private string $hash)
            {
            }

            public function getHash(): string
            {
                return $this->hash;
            }
        });

        return $container;
    }

    private function createContainerForCompressCssJs(string $environment, string $root): Container
    {
        $container = $this->createContainerWithGit('abcdef');
        $container->setParameter('kernel.environment', $environment);
        $container->setParameter('aurora.root', $root);
        $container->set('aurora.sanitizer', new class extends AuroraSanitizer {
            public function cssMinify(string $css, ?string $asset = null)
            {
                return sprintf('[%s|%s]', $css, $asset);
            }
        });

        return $container;
    }

    /**
     * Capture the context of the only template displayed
     *
     * @param array<string, mixed>|null $context
     */
    private function createDisplayingTwig(string $template, ?array &$context): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('display')
            ->with($template, $this->callback(static function (array $displayedContext) use (&$context): bool {
                $context = $displayedContext;

                return true;
            }));

        return $twig;
    }

    /**
     * @param array<string, string> $assets paths relative to the public directory
     */
    private function createProjectWithAssets(array $assets): string
    {
        $root = $this->createTemporaryDirectory();

        foreach ($assets as $path => $content) {
            if (!is_dir(dirname($root . '/public/' . $path))) {
                mkdir(dirname($root . '/public/' . $path), 0777, true);
            }

            file_put_contents($root . '/public/' . $path, $content);
        }

        return $root;
    }

    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/aurora-utility-extension-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            is_dir($directory . '/' . $item) ? $this->removeDirectory($directory . '/' . $item) : @unlink($directory . '/' . $item);
        }

        @rmdir($directory);
    }

    private function createUtilityExtensionWithGitHash(string $hash): UtilityExtension
    {
        $container = new Container();
        $container->set('aurora.git', new class($hash) {
            public function __construct(private string $hash)
            {
            }

            public function getHash(): string
            {
                return $this->hash;
            }
        });

        return new UtilityExtension(
            $container,
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );
    }
}
