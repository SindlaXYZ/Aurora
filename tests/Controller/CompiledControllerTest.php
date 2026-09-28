<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Controller\CompiledController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

class CompiledControllerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sprintf('%s/aurora-compiled-controller-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        mkdir($this->tmpDir . '/compiled', 0777, true);
        file_put_contents($this->tmpDir . '/compiled/app.css', 'body{color:red}');
        file_put_contents($this->tmpDir . '/compiled/app.js', 'console.log(1);');
    }

    protected function tearDown(): void
    {
        foreach (['app.css', 'app.js'] as $fileName) {
            @unlink(sprintf('%s/compiled/%s', $this->tmpDir, $fileName));
        }

        @rmdir($this->tmpDir . '/compiled');
        @rmdir($this->tmpDir);
    }

    #[DataProvider('dataFiles')]
    public function testServesTheCompiledFile(string $fileName, int $expectedStatus, string $expectedContent, string $expectedContentType): void
    {
        $container = new Container();
        // A trailing slash, as in "%kernel.project_dir%/var/tmp/"
        $container->setParameter('aurora.tmp', $this->tmpDir . '/');

        $controller = new CompiledController();
        $controller->setContainer($container);

        $response = $controller->cssJsFiles(Request::create('/aurora/compiled/' . $fileName), $fileName);

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $this->assertSame($expectedContent, $response->getContent());
        $this->assertSame($expectedContentType, $response->headers->get('Content-Type'));
        // Already minified: the OutputSubscriber must not touch it
        $this->assertSame('true', $response->headers->get('X-Do-Not-Minify'));
    }

    public static function dataFiles(): iterable
    {
        yield 'CSS file' => ['app.css', 200, 'body{color:red}', 'text/css'];
        yield 'JS file' => ['app.js', 200, 'console.log(1);', 'text/javascript'];
        yield 'missing JS file' => ['missing.js', 404, '/* File missing.js not found */', 'text/javascript'];
        yield 'missing CSS file' => ['missing.css', 404, '/* File missing.css not found */', 'text/css'];
    }
}
