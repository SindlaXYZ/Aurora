<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use Aws\Handler\Guzzle\GuzzleHandler;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Query;
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

    public function testListWithAPrefixReadsEveryPage(): void
    {
        $tester = $this->commandTester([
            $this->listObjectsResponse([
                ['snapshots/2026-10-20/database.sql', '2026-10-20T10:00:00.000Z', 2048],
                ['snapshots/2026-10-21/database.sql', '2026-10-21T10:00:00.000Z', 1024],
            ], true, 'page/2+token=='),
            $this->listObjectsResponse([
                ['snapshots/2026-10-22/database.sql', '2026-10-22T10:00:00.000Z', 512],
            ]),
        ]);

        // No "/" at the end: the prefix matches the keys of every "directory" from 2026-10-20 to 2026-10-29
        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list', '--prefix' => 'snapshots/2026-10-2']));

        // The full keys of both pages, prefix included, in a single table
        $display = $tester->getDisplay();
        $this->assertSame(
            ['snapshots/2026-10-20/database.sql', 'snapshots/2026-10-21/database.sql', 'snapshots/2026-10-22/database.sql'],
            array_column($this->tableRows($display), 0)
        );
        $this->assertSame(1, preg_match_all('/^\| Key /m', $display));

        $queries = $this->queries();
        $this->assertCount(2, $queries);
        foreach ($queries as $query) {
            $this->assertSame('2', $query['list-type'] ?? null);
            $this->assertSame('snapshots/2026-10-2', $query['prefix'] ?? null);
            $this->assertArrayNotHasKey('delimiter', $query);
        }
        $this->assertArrayNotHasKey('continuation-token', $queries[0]);
        $this->assertSame('page/2+token==', $queries[1]['continuation-token'] ?? null);
    }

    public function testListSortsTheObjectsOfEveryPage(): void
    {
        $tester = $this->commandTester([
            $this->listObjectsResponse([
                ['a.sql', '2026-01-02T10:00:00.000Z', 512],
                ['b.sql', '2026-01-03T10:00:00.000Z', 512],
            ], true, 't1'),
            $this->listObjectsResponse([
                ['c.sql', '2026-01-01T10:00:00.000Z', 512],
            ]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list', '--orderBy' => 'LastModified']));
        // The oldest object is on the second page: the rows of every page are sorted together, then rendered once
        $this->assertSame(['c.sql', 'a.sql', 'b.sql'], array_column($this->tableRows($tester->getDisplay()), 0));
        $this->assertSame(1, preg_match_all('/^\| Key /m', $tester->getDisplay()));
        $this->assertCount(2, $this->history);
    }

    public function testListWithoutAPrefixReadsEveryPageOfTheBucket(): void
    {
        $tester = $this->commandTester([
            $this->listObjectsResponse([['a.sql', '2026-01-01T10:00:00.000Z', 1]], true, 't1'),
            $this->listObjectsResponse([['b.sql', '2026-01-01T10:00:00.000Z', 2]], true, 't2'),
            $this->listObjectsResponse([['c.sql', '2026-01-01T10:00:00.000Z', 3]]),
        ]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list']));
        $this->assertSame(['a.sql', 'b.sql', 'c.sql'], array_column($this->tableRows($tester->getDisplay()), 0));

        $queries = $this->queries();
        $this->assertCount(3, $queries);
        foreach ($queries as $query) {
            $this->assertSame('2', $query['list-type'] ?? null);
            $this->assertArrayNotHasKey('prefix', $query);
            $this->assertArrayNotHasKey('delimiter', $query);
        }
        $this->assertArrayNotHasKey('continuation-token', $queries[0]);
        $this->assertSame('t1', $queries[1]['continuation-token'] ?? null);
        $this->assertSame('t2', $queries[2]['continuation-token'] ?? null);
    }

    public function testListOfAPrefixWithoutKeysRendersOnlyTheHeader(): void
    {
        $tester = $this->commandTester([$this->listObjectsResponse([])]);

        // An empty result is not an error
        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list', '--prefix' => 'snapshots/current/']));
        $this->assertStringContainsString('| Key | LastModified | Ago | ETag | Bytes | Size | StorageClass |', $tester->getDisplay());
        $this->assertSame([], $this->tableRows($tester->getDisplay()));
        $this->assertCount(1, $this->history);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function dataLiteralPrefix(): iterable
    {
        yield 'zero' => ['0'];
        yield 'a trailing slash' => ['snapshots/current/'];
        yield 'a leading slash' => ['/snapshots/'];
        yield 'a single space' => [' '];
        yield 'spaces at both ends' => [' snapshots/x '];
    }

    #[DataProvider('dataLiteralPrefix')]
    public function testListSendsThePrefixAsGiven(string $prefix): void
    {
        $tester = $this->commandTester([$this->listObjectsResponse([])]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'list', '--prefix' => $prefix]));

        $queries = $this->queries();
        $this->assertCount(1, $queries);
        $this->assertSame($prefix, $queries[0]['prefix'] ?? null);
        $this->assertArrayNotHasKey('delimiter', $queries[0]);
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function dataEmptyPrefix(): iterable
    {
        yield '"--prefix=", an empty value' => [''];
        yield '"--prefix" without a value' => [null];
    }

    /**
     * An empty prefix would list the whole bucket: a caller deleting every listed key would empty it
     */
    #[DataProvider('dataEmptyPrefix')]
    public function testListRefusesAnEmptyPrefix(?string $prefix): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The "--prefix" option must not be empty.');

        try {
            $this->commandTester([$this->listObjectsResponse([])], false)->execute(['--action' => 'list', '--prefix' => $prefix]);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function dataMissingContinuationToken(): iterable
    {
        yield 'no NextContinuationToken' => [null];
        yield 'an empty NextContinuationToken' => [''];
    }

    /**
     * Stopping at the truncated page would silently hide the rest of the keys
     */
    #[DataProvider('dataMissingContinuationToken')]
    public function testListRefusesATruncatedPageWithoutAContinuationToken(?string $nextToken): void
    {
        $tester = $this->commandTester([
            $this->listObjectsResponse([['a.sql', '2026-01-01T10:00:00.000Z', 1]], true, $nextToken),
            $this->listObjectsResponse([['b.sql', '2026-01-01T10:00:00.000Z', 2]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ListObjectsV2 returned a truncated page without a new continuation token.');

        try {
            $tester->execute(['--action' => 'list']);
        } finally {
            $this->assertCount(1, $this->history);
            $this->assertDoesNotMatchRegularExpression('/^\|/m', $tester->getDisplay());
        }
    }

    /**
     * @return iterable<string, array{0: list<string>}>
     */
    public static function dataRepeatedContinuationToken(): iterable
    {
        yield 'the token of the previous page' => [['t1', 't1']];
        // A guard comparing the token only with the last one sent would go on
        yield 'the token of an earlier page' => [['t1', 't2', 't1']];
    }

    /**
     * A token already sent would list the same pages again, forever
     *
     * @param list<string> $nextTokens the NextContinuationToken of each truncated page
     */
    #[DataProvider('dataRepeatedContinuationToken')]
    public function testListRefusesARepeatedContinuationToken(array $nextTokens): void
    {
        $responses = [];
        foreach ($nextTokens as $page => $nextToken) {
            $responses[] = $this->listObjectsResponse([[sprintf('page-%d.sql', $page), '2026-01-01T10:00:00.000Z', 1]], true, $nextToken);
        }
        $responses[] = $this->listObjectsResponse([['last-page.sql', '2026-01-01T10:00:00.000Z', 1]]);
        $tester      = $this->commandTester($responses);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ListObjectsV2 returned a truncated page without a new continuation token.');

        try {
            $tester->execute(['--action' => 'list']);
        } finally {
            $this->assertCount(count($nextTokens), $this->history);
            $this->assertDoesNotMatchRegularExpression('/^\|/m', $tester->getDisplay());
        }
    }

    public function testDeleteRemovesExactlyTheRemoteFile(): void
    {
        $tester = $this->commandTester([new Response(204)]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'delete', '--remoteFile' => 'snapshots/current/database.sql']));
        $this->assertCount(1, $this->history);

        // DeleteObject (DeleteObjects is a POST) of exactly the given key, slashes kept
        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame('bucket.r2.example.com', $request->getUri()->getHost());
        $this->assertSame('/snapshots/current/database.sql', $request->getUri()->getPath());
        // Nothing is rendered but the "Start running" and "Done in" lines
        $this->assertMatchesRegularExpression('/\A\[[\d:]{8}\] Start running [^\n]+\n\[[\d:]{8}\] Done in [^\n]+\n\z/', $tester->getDisplay(true));
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function dataDeleteWithoutARemoteFile(): iterable
    {
        yield 'no remote file' => [[]];
        yield 'empty remote file' => [['--remoteFile' => '']];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('dataDeleteWithoutARemoteFile')]
    public function testDeleteRequiresARemoteFileName(array $options): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Please provide a remote file name!');

        try {
            $this->commandTester([new Response(204)], false)->execute(['--action' => 'delete'] + $options);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    /**
     * @return iterable<string, array{0: array<string, string|null>}>
     */
    public static function dataDeleteWithAPrefix(): iterable
    {
        yield 'a prefix and a remote file' => [['--prefix' => 'x', '--remoteFile' => 'y']];
        yield 'a prefix without a remote file' => [['--prefix' => 'x']];
        yield 'a prefix without a value' => [['--prefix' => null, '--remoteFile' => 'y']];
    }

    /**
     * Not a delete of every key under a prefix: "--prefix" is refused, not ignored
     *
     * @param array<string, string|null> $options
     */
    #[DataProvider('dataDeleteWithAPrefix')]
    public function testDeleteRefusesAPrefix(array $options): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The "--prefix" option can only be used with "--action=list".');

        try {
            $this->commandTester([new Response(204)], false)->execute(['--action' => 'delete'] + $options);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    public function testDeleteDoesNotCatchTheS3Error(): void
    {
        $tester = $this->commandTester([new Response(404, ['Content-Type' => 'application/xml'], '<Error><Code>NoSuchKey</Code></Error>')]);

        try {
            $tester->execute(['--action' => 'delete', '--remoteFile' => 'snapshots/current/missing.sql']);
            $this->fail('A failed delete is expected to throw.');
        } catch (S3Exception $e) {
            $this->assertSame('NoSuchKey', $e->getAwsErrorCode());
        }

        $this->assertCount(1, $this->history);
        $this->assertSame('DELETE', $this->history[0]['request']->getMethod());
    }

    public function testThePrefixOptionIsDeclared(): void
    {
        $definition = new CloudflareR2Command($this->createStub(AuroraCloudflareR2::class))->getDefinition();

        // Listed by "--help" as "--prefix[=PREFIX]": the sign that "list --prefix" and "delete" are available
        $this->assertTrue($definition->hasOption('prefix'));
        $this->assertTrue($definition->getOption('prefix')->isValueOptional());
    }

    public function testThePrefixHelperIsNotAnAction(): void
    {
        $tester = $this->commandTester([]);

        // Every method that is not private and has no required parameter is an action: the helper has to stay private
        $this->assertSame(Command::FAILURE, $tester->execute(['--action' => 'isPrefixGiven']));
        $this->assertStringContainsString('Invalid action isPrefixGiven()', $tester->getDisplay());
        $this->assertSame([], $this->history);
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
     * A refused input must fail before the client is created: with $clientCreated = false, createClient() is never expected. The test of a
     * refused input still queues a valid response and checks "$this->history === []": a request sent to an empty queue throws unrecorded.
     *
     * @param list<Response> $responses
     */
    private function commandTester(array $responses, bool $clientCreated = true): CommandTester
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

        if ($clientCreated) {
            $cloudflareR2 = $this->createStub(AuroraCloudflareR2::class);
            $cloudflareR2->method('createClient')->willReturn($s3Client);
        } else {
            $cloudflareR2 = $this->createMock(AuroraCloudflareR2::class);
            $cloudflareR2->expects($this->never())->method('createClient');
        }
        $cloudflareR2->method('getBucket')->willReturn('bucket');

        return new CommandTester(new CloudflareR2Command($cloudflareR2));
    }

    /**
     * A ListObjectsV2 page: a null $nextToken leaves out the NextContinuationToken element, "" renders it empty
     *
     * @param list<array{0: string, 1: string, 2: int}> $objects [Key, LastModified, Size]
     */
    private function listObjectsResponse(array $objects, bool $isTruncated = false, ?string $nextToken = null): Response
    {
        $contents = '';
        foreach ($objects as [$key, $lastModified, $size]) {
            $contents .= sprintf(
                '<Contents><Key>%1$s</Key><LastModified>%2$s</LastModified><ETag>"etag-%1$s"</ETag><Size>%3$d</Size>'
                . '<StorageClass>STANDARD</StorageClass></Contents>',
                htmlspecialchars($key, ENT_XML1),
                $lastModified,
                $size
            );
        }

        return new Response(200, ['Content-Type' => 'application/xml'], sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><Name>bucket</Name>'
            . '<KeyCount>%d</KeyCount><MaxKeys>1000</MaxKeys><IsTruncated>%s</IsTruncated>%s%s</ListBucketResult>',
            count($objects),
            $isTruncated ? 'true' : 'false',
            null === $nextToken ? '' : sprintf('<NextContinuationToken>%s</NextContinuationToken>', htmlspecialchars($nextToken, ENT_XML1)),
            $contents
        ));
    }

    /**
     * The decoded query of every request sent: the SDK encodes the "/" of a prefix as "%2F"
     *
     * @return list<array<string, mixed>>
     */
    private function queries(): array
    {
        return array_values(array_map(
            static fn(array $transaction): array => Query::parse($transaction['request']->getUri()->getQuery()),
            $this->history
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
