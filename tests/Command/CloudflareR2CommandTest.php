<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sindla\Bundle\AuroraBundle\Command\CloudflareR2Command;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2\AuroraCloudflareR2;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CloudflareR2CommandTest extends TestCase
{
    private string $dir;

    /**
     * @var array<int, array{request: RequestInterface}>
     */
    private array $history = [];

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

    public function testTestActionReportsThatTheCommandWorks(): void
    {
        $tester = $this->commandTester([]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'test']));
        $this->assertMatchesRegularExpression('/^\[\d{2}:\d{2}:\d{2}\] \[aurora:cloudflare:r2\] It works!$/m', $tester->getDisplay());
        $this->assertSame([], $this->history);
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

    /**
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function dataDownloadMissingOptions(): iterable
    {
        yield 'no remote file' => [['--localFile' => 'database.sql'], 'Please provide a remote file name!'];
        yield 'empty remote file' => [['--remoteFile' => '', '--localFile' => 'database.sql'], 'Please provide a remote file name!'];
        yield 'no local file' => [['--remoteFile' => 'database.sql'], 'Please provide a local file name!'];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('dataDownloadMissingOptions')]
    public function testDownloadRequiresBothFileNames(array $options, string $expectedMessage): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        try {
            $this->commandTester([])->execute(['--action' => 'download'] + $options);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    public function testUploadSendsTheLocalFileToTheBucket(): void
    {
        file_put_contents($this->dir . '/database.sql', 'local dump');

        $exitCode = $this->commandTester([new Response(200, ['ETag' => '"etag"'])])->execute([
            '--action'     => 'upload',
            '--localFile'  => $this->dir . '/database.sql',
            '--remoteFile' => 'backups/database.sql',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('bucket.r2.example.com', $request->getUri()->getHost());
        $this->assertSame('/backups/database.sql', $request->getUri()->getPath());
        $this->assertSame('local dump', (string)$request->getBody());
    }

    public function testUploadRequiresAnExistingLocalFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('File "%s/missing.sql" not found!', $this->dir));

        $this->commandTester([])->execute([
            '--action'     => 'upload',
            '--localFile'  => $this->dir . '/missing.sql',
            '--remoteFile' => 'database.sql',
        ]);
    }

    public function testUploadRequiresARemoteFileName(): void
    {
        file_put_contents($this->dir . '/database.sql', 'local dump');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Please provide a remote file name!');

        try {
            $this->commandTester([])->execute(['--action' => 'upload', '--localFile' => $this->dir . '/database.sql']);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: list<string>}>
     */
    public static function dataListOrder(): iterable
    {
        // Keys: "b.sql" (2048 B, 2026-01-02), "A.sql" (512 B, 2026-01-03), "c.sql" (2048 B, 2026-01-01)
        yield 'by key (default), case-insensitive' => [[], ['A.sql', 'b.sql', 'c.sql']];
        yield 'by key, descending (case-insensitive direction)' => [['--orderDir' => 'DESC'], ['c.sql', 'b.sql', 'A.sql']];
        yield 'by size, ascending, stable on ties' => [['--orderBy' => 'Size'], ['A.sql', 'b.sql', 'c.sql']];
        yield 'by bytes, an alias of size' => [['--orderBy' => 'Bytes', '--orderDir' => 'desc'], ['b.sql', 'c.sql', 'A.sql']];
        yield 'by last modified' => [['--orderBy' => 'LastModified'], ['c.sql', 'b.sql', 'A.sql']];
        yield 'by last modified, descending' => [['--orderBy' => 'LastModified', '--orderDir' => 'desc'], ['A.sql', 'b.sql', 'c.sql']];
        yield 'unknown column falls back to the key' => [['--orderBy' => 'Owner', '--orderDir' => 'sideways'], ['A.sql', 'b.sql', 'c.sql']];
    }

    /**
     * @param array<string, string> $options
     * @param list<string>          $expectedKeys
     */
    #[DataProvider('dataListOrder')]
    public function testListSortsTheObjects(array $options, array $expectedKeys): void
    {
        $tester = $this->commandTester([$this->listObjectsResponse([
            ['b.sql', '2026-01-02T10:00:00.000Z', 2048],
            ['A.sql', '2026-01-03T10:00:00.000Z', 512],
            ['c.sql', '2026-01-01T10:00:00.000Z', 2048],
        ])]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list'] + $options));
        $this->assertSame($expectedKeys, array_column($this->tableRows($tester->getDisplay()), 0));

        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('bucket.r2.example.com', $request->getUri()->getHost());
        $this->assertStringContainsString('list-type=2', $request->getUri()->getQuery());
    }

    public function testListRendersEveryColumn(): void
    {
        $lastModified = new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->modify('-3 days -5 hours -30 minutes');

        $tester = $this->commandTester([$this->listObjectsResponse([
            ['database.sql', $lastModified->format('Y-m-d\TH:i:s.000\Z'), 1536],
        ])]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list']));

        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/\| Key\s+\| LastModified\s+\| Ago\s+\| ETag\s+\| Bytes\s+\| Size\s+\| StorageClass \|/', $display);

        $rows = $this->tableRows($display);
        $this->assertCount(1, $rows);
        $this->assertSame('database.sql', $rows[0][0]);
        $this->assertSame($lastModified->format('Y-m-d H:i:s'), $rows[0][1]);
        $this->assertMatchesRegularExpression('/^3 days and [4-6] hours ago$/', $rows[0][2]);
        $this->assertSame('"etag-database.sql"', $rows[0][3]);
        $this->assertSame(['1536', '1.50K', 'STANDARD'], array_slice($rows[0], 4));
        // Bytes and Size are right-aligned
        $this->assertMatchesRegularExpression('/\|\s+1536 \|\s+1\.50K \| STANDARD\s+\|/', $display);
    }

    public function testListOfAnEmptyBucketRendersOnlyTheHeader(): void
    {
        $tester = $this->commandTester([$this->listObjectsResponse([])]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list']));
        $this->assertStringContainsString('| Key | LastModified | Ago | ETag | Bytes | Size | StorageClass |', $tester->getDisplay());
        $this->assertSame([], $this->tableRows($tester->getDisplay()));
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: string}>
     */
    public static function dataHumanFilesize(): iterable
    {
        yield 'zero bytes' => [0, 2, '0.00B'];
        yield 'bytes' => [512, 2, '512.00B'];
        yield 'one kibibyte' => [1024, 2, '1.00K'];
        yield 'mebibytes' => [5 * 1024 * 1024, 2, '5.00M'];
        yield 'gibibytes' => [3 * 1024 ** 3 + 512 * 1024 ** 2, 1, '3.5G'];
        yield 'tebibytes' => [2 * 1024 ** 4, 0, '2T'];
    }

    #[DataProvider('dataHumanFilesize')]
    public function testHumanFilesize(int $bytes, int $decimals, string $expected): void
    {
        $method = new \ReflectionMethod(CloudflareR2Command::class, 'humanFilesize');

        $this->assertSame($expected, $method->invoke(new CloudflareR2Command($this->createStub(AuroraCloudflareR2::class)), $bytes, $decimals));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function dataFormatRelativeTime(): iterable
    {
        yield 'same instant' => ['2026-05-10 12:00:00', '2026-05-10 12:00:00', 'just now'];
        yield 'one second' => ['2026-05-10 11:59:59', '2026-05-10 12:00:00', '1 second ago'];
        yield 'seconds' => ['2026-05-10 11:59:15', '2026-05-10 12:00:00', '45 seconds ago'];
        yield 'minutes never show the seconds' => ['2026-05-10 11:54:30', '2026-05-10 12:00:00', '5 minutes ago'];
        yield 'hours and minutes' => ['2026-05-10 10:59:00', '2026-05-10 12:00:00', '1 hour and 1 minute ago'];
        yield 'hours, zero minutes omitted' => ['2026-05-10 09:59:50', '2026-05-10 12:00:00', '2 hours ago'];
        yield 'days and hours' => ['2026-05-07 08:00:00', '2026-05-10 12:00:00', '3 days and 4 hours ago'];
        yield 'months and days' => ['2026-03-05 12:00:00', '2026-05-10 12:00:00', '2 months and 5 days ago'];
        yield 'one year and one month' => ['2025-04-10 12:00:00', '2026-05-10 12:00:00', '1 year and 1 month ago'];
        yield 'years, zero months omitted' => ['2023-05-10 12:00:00', '2026-05-10 12:00:00', '3 years ago'];
        yield 'future' => ['2026-05-10 14:30:00', '2026-05-10 12:00:00', 'in 2 hours and 30 minutes'];
    }

    #[DataProvider('dataFormatRelativeTime')]
    public function testFormatRelativeTime(string $when, string $now, string $expected): void
    {
        $utc    = new \DateTimeZone('UTC');
        $method = new \ReflectionMethod(CloudflareR2Command::class, 'formatRelativeTime');

        $this->assertSame(
            $expected,
            $method->invoke(
                new CloudflareR2Command($this->createStub(AuroraCloudflareR2::class)),
                new \DateTimeImmutable($when, $utc),
                new \DateTimeImmutable($now, $utc)
            )
        );
    }

    private function download(Response $response): int
    {
        return $this->commandTester([$response])->execute([
            '--action'     => 'download',
            '--remoteFile' => 'database.sql',
            '--localFile'  => $this->dir . '/database.sql',
        ]);
    }

    /**
     * The R2 client is a real S3Client whose HTTP layer is a Guzzle mock: the requests it sends are recorded in $this->history
     *
     * @param list<Response> $responses
     */
    private function commandTester(array $responses): CommandTester
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($this->history));

        $s3Client = new S3Client([
            'region'       => 'auto',
            'endpoint'     => 'https://r2.example.com',
            'version'      => 'latest',
            'credentials'  => ['key' => 'key', 'secret' => 'secret'],
            'retries'      => 0,
            'http_handler' => new GuzzleHandler(new Client(['handler' => $handlerStack])),
        ]);

        $cloudflareR2 = $this->createStub(AuroraCloudflareR2::class);
        $cloudflareR2->method('createClient')->willReturn($s3Client);
        $cloudflareR2->method('getBucket')->willReturn('bucket');

        return new CommandTester(new CloudflareR2Command($cloudflareR2));
    }

    /**
     * @param list<array{0: string, 1: string, 2: int}> $objects [Key, LastModified, Size]
     */
    private function listObjectsResponse(array $objects): Response
    {
        $contents = '';
        foreach ($objects as [$key, $lastModified, $size]) {
            $contents .= sprintf(
                '<Contents><Key>%1$s</Key><LastModified>%2$s</LastModified><ETag>"etag-%1$s"</ETag><Size>%3$d</Size>'
                . '<StorageClass>STANDARD</StorageClass></Contents>',
                $key,
                $lastModified,
                $size
            );
        }

        return new Response(200, ['Content-Type' => 'application/xml'], sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Name>bucket</Name>'
            . '<KeyCount>%d</KeyCount><MaxKeys>1000</MaxKeys><IsTruncated>false</IsTruncated>%s</ListBucketResult>',
            count($objects),
            $contents
        ));
    }

    /**
     * The trimmed cells of the body rows of the rendered table
     *
     * @return list<list<string>>
     */
    private function tableRows(string $display): array
    {
        $rows = [];
        foreach (explode("\n", $display) as $line) {
            if (!str_starts_with($line, '|') || str_starts_with($line, '| Key ')) {
                continue;
            }

            $rows[] = array_map('trim', explode('|', trim($line, '|')));
        }

        return $rows;
    }
}
