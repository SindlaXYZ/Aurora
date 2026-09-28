<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\CloudflareR2Command;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2\AuroraCloudflareR2;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CloudflareR2CommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/aurora-r2-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testDownloadReplacesTheLocalFile(): void
    {
        file_put_contents($this->dir . '/database.sql', 'old dump');
        chmod($this->dir . '/database.sql', 0600);

        $exitCode = $this->download(new Response(200, [], 'new dump'));

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame('new dump', file_get_contents($this->dir . '/database.sql'));
        // The permissions of the replaced file are kept (a private database dump must not become readable by everyone)
        clearstatcache();
        $this->assertSame(0600, fileperms($this->dir . '/database.sql') & 0777);
        $this->assertSame(['database.sql'], array_values(array_diff(scandir($this->dir), ['.', '..'])));
    }

    public function testDownloadKeepsTheOwnerOfTheLocalFile(): void
    {
        if (!function_exists('posix_geteuid') || 0 !== posix_geteuid()) {
            $this->markTestSkipped('Only a privileged user can create a file of another user.');
        }

        file_put_contents($this->dir . '/database.sql', 'old dump');
        chown($this->dir . '/database.sql', 65534);
        chgrp($this->dir . '/database.sql', 65534);
        chmod($this->dir . '/database.sql', 0640);

        $this->assertSame(Command::SUCCESS, $this->download(new Response(200, [], 'new dump')));

        // Replaced by root, the dump of the application user used to be "root:root 0640": the application could no longer read it
        clearstatcache();
        $stat = stat($this->dir . '/database.sql');
        $this->assertSame('new dump', file_get_contents($this->dir . '/database.sql'));
        $this->assertSame([65534, 65534, 0640], [$stat['uid'], $stat['gid'], $stat['mode'] & 0777]);
    }

    /**
     * "SaveAs" wrote the error body over the local file (e.g. the 404 of a typo in "--remoteFile"): the dump about to be imported was lost
     */
    public function testAFailedDownloadKeepsTheLocalFile(): void
    {
        file_put_contents($this->dir . '/database.sql', 'old dump');

        try {
            $this->download(new Response(404, ['Content-Type' => 'application/xml'], '<Error><Code>NoSuchKey</Code></Error>'));
            $this->fail('A failed download is expected to throw.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('NoSuchKey', $e->getMessage());
        }

        $this->assertSame('old dump', file_get_contents($this->dir . '/database.sql'));
        $this->assertSame(['database.sql'], array_values(array_diff(scandir($this->dir), ['.', '..'])));
    }

    private function download(Response $response): int
    {
        $s3Client = new S3Client([
            'region'       => 'auto',
            'endpoint'     => 'https://r2.example.com',
            'version'      => 'latest',
            'credentials'  => ['key' => 'key', 'secret' => 'secret'],
            'retries'      => 0,
            'http_handler' => new GuzzleHandler(new Client(['handler' => HandlerStack::create(new MockHandler([$response]))])),
        ]);

        $cloudflareR2 = $this->createStub(AuroraCloudflareR2::class);
        $cloudflareR2->method('createClient')->willReturn($s3Client);
        $cloudflareR2->method('getBucket')->willReturn('bucket');

        return new CommandTester(new CloudflareR2Command($cloudflareR2))->execute([
            '--action'     => 'download',
            '--remoteFile' => 'database.sql',
            '--localFile'  => $this->dir . '/database.sql',
        ]);
    }
}
