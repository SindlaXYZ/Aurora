<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPHPUnitCodeCoverageBadge;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadgeTest extends TestCase
{
    public function testStatementsBadgeUsesCorrectRightBlockWidth(): void
    {
        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory = sys_get_temp_dir() . '/aurora_badge_' . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        $cloverFilePath     = $tempDirectory . '/clover.xml';
        $coverageSvgPath    = $tempDirectory . '/coverage.svg';
        $statementsSvgPath  = $tempDirectory . '/statements.svg';

        $cloverXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
    <project>
        <metrics elements="10" coveredelements="6"/>
        <file name="Sample.php">
            <metrics statements="5" coveredstatements="3"/>
        </file>
    </project>
</coverage>
XML;

        file_put_contents($cloverFilePath, $cloverXml);

        try {
            $badge->generateCoverageBadges($cloverFilePath, $coverageSvgPath, $statementsSvgPath);

            $statementsSvg = file_get_contents($statementsSvgPath);
            self::assertIsString($statementsSvg);
            self::assertStringContainsString('M75 0h85v20H75z', $statementsSvg);
        } finally {
            @unlink($cloverFilePath);
            @unlink($coverageSvgPath);
            @unlink($statementsSvgPath);
            @rmdir($tempDirectory);
        }
    }

    public function testAppendCoverageHistoryAppendsAndSkipsConsecutiveDuplicates(): void
    {
        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory = sys_get_temp_dir() . '/aurora_history_' . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        $cloverFilePath  = $tempDirectory . '/clover.xml';
        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';

        file_put_contents($cloverFilePath, $this->cloverXml(10, 6, 5, 3));

        try {
            self::assertTrue($badge->appendCoverageHistory($cloverFilePath, $historyFilePath, 'abcdef0123456789', '2026-06-01'));

            // Same clover values: the entry must be skipped as a consecutive duplicate
            self::assertFalse($badge->appendCoverageHistory($cloverFilePath, $historyFilePath, 'abcdef0123456789', '2026-06-02'));

            $lines = array_values(array_filter(explode("\n", (string)file_get_contents($historyFilePath))));
            self::assertCount(1, $lines);

            self::assertSame(
                ['date' => '2026-06-01', 'sha' => 'abcdef0', 'coverage' => 60, 'statements' => 5, 'coveredStatements' => 3],
                json_decode($lines[0], true)
            );

            // Different clover values: a new entry must be appended
            file_put_contents($cloverFilePath, $this->cloverXml(10, 8, 5, 4));
            self::assertTrue($badge->appendCoverageHistory($cloverFilePath, $historyFilePath, null, '2026-06-03'));

            $lines = array_values(array_filter(explode("\n", (string)file_get_contents($historyFilePath))));
            self::assertCount(2, $lines);

            self::assertSame(
                ['date' => '2026-06-03', 'sha' => null, 'coverage' => 80, 'statements' => 5, 'coveredStatements' => 4],
                json_decode($lines[1], true)
            );
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageTrendBadgeRendersTheHistorySeries(): void
    {
        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory = sys_get_temp_dir() . '/aurora_trend_' . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';
        $trendFilePath   = $tempDirectory . '/coverage-trend.svg';

        file_put_contents($historyFilePath, implode("\n", [
            '{"date":"2026-05-01","sha":null,"coverage":50,"statements":100,"coveredStatements":50}',
            '{"date":"2026-05-15","sha":"abc1234","coverage":75,"statements":120,"coveredStatements":90}',
            '{"date":"2026-06-01","sha":"def5678","coverage":84,"statements":130,"coveredStatements":109}',
        ]) . "\n");

        try {
            $badge->generateCoverageTrendBadge($historyFilePath, $trendFilePath);

            $trendSvg = file_get_contents($trendFilePath);
            self::assertIsString($trendSvg);
            self::assertStringContainsString('<polyline', $trendSvg);
            self::assertStringContainsString('84% &#183; 109/130', $trendSvg);
            self::assertStringContainsString('#68CB0A', $trendSvg); // accent color for 84% coverage
            self::assertStringContainsString('>2026-05-01<', $trendSvg);
            self::assertStringContainsString('>2026-06-01<', $trendSvg);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageTrendBadgeRequiresHistoryEntries(): void
    {
        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory = sys_get_temp_dir() . '/aurora_trend_' . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        try {
            $this->expectException(\InvalidArgumentException::class);
            $badge->generateCoverageTrendBadge($tempDirectory . '/missing.ndjson', $tempDirectory . '/coverage-trend.svg');
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testBackfillCoverageHistoryFromGitRebuildsTheHistory(): void
    {
        if (0 !== $this->runGit(['--version'], sys_get_temp_dir())[0]) {
            self::markTestSkipped('The "git" binary is not available.');
        }

        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory   = sys_get_temp_dir() . '/aurora_backfill_' . uniqid('', true);
        $badgesDirectory = $tempDirectory . '/.github/badges';
        self::assertTrue(mkdir($badgesDirectory, 0777, true));

        $historyFilePath = $badgesDirectory . '/coverage-trend.ndjson';

        try {
            self::assertSame(0, $this->runGit(['init', '-q', '.'], $tempDirectory)[0]);

            // First data point
            file_put_contents($badgesDirectory . '/statements.svg', $this->statementsSvg(10, 20));
            file_put_contents($badgesDirectory . '/coverage.svg', $this->coverageSvg(50));
            $this->gitCommit($tempDirectory, 'First badge values');

            // Second data point
            file_put_contents($badgesDirectory . '/statements.svg', $this->statementsSvg(15, 20));
            file_put_contents($badgesDirectory . '/coverage.svg', $this->coverageSvg(75));
            $this->gitCommit($tempDirectory, 'Second badge values');

            // Whitespace-only change: same values, must be collapsed by the consecutive de-duplication
            file_put_contents($badgesDirectory . '/statements.svg', $this->statementsSvg(15, 20) . "\n");
            $this->gitCommit($tempDirectory, 'Whitespace only change');

            self::assertSame(2, $badge->backfillCoverageHistoryFromGit($historyFilePath));

            $lines = array_values(array_filter(explode("\n", (string)file_get_contents($historyFilePath))));
            self::assertCount(2, $lines);

            $firstEntry = json_decode($lines[0], true);
            self::assertSame(50, $firstEntry['coverage']);
            self::assertSame(20, $firstEntry['statements']);
            self::assertSame(10, $firstEntry['coveredStatements']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $firstEntry['date']);
            self::assertMatchesRegularExpression('/^[0-9a-f]{7}$/', $firstEntry['sha']);

            $secondEntry = json_decode($lines[1], true);
            self::assertSame(75, $secondEntry['coverage']);
            self::assertSame(20, $secondEntry['statements']);
            self::assertSame(15, $secondEntry['coveredStatements']);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    #[DataProvider('dataPHPUnitTestsBadge')]
    public function testGeneratePHPUnitTestsBadgeShowsWhetherTheSuitePasses(int $failures, int $errors, string $status, string $width, string $colorA, string $colorB, string $textX): void
    {
        $tempDirectory = $this->createTempDirectory('aurora_junit_');
        $junitFilePath = $tempDirectory . '/junit.xml';
        $badgeFilePath = $tempDirectory . '/phpunit.svg';

        file_put_contents($junitFilePath, $this->junitXml(10, $failures, $errors, 0));

        try {
            new AuroraPHPUnitCodeCoverageBadge()->generatePHPUnitTestsBadge($junitFilePath, $badgeFilePath);

            $badgeSvg = (string)file_get_contents($badgeFilePath);
            self::assertNotFalse(simplexml_load_string($badgeSvg));
            self::assertStringStartsWith(sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="20">', $width), $badgeSvg);
            self::assertStringContainsString(sprintf('<title>PHPUnit - %s</title>', $status), $badgeSvg);
            self::assertStringContainsString(sprintf('<stop stop-color="%s" offset="0%%"></stop>', $colorA), $badgeSvg);
            self::assertStringContainsString(sprintf('<stop stop-color="%s" offset="100%%"></stop>', $colorB), $badgeSvg);
            self::assertStringContainsString(sprintf('<tspan x="%s" y="14">%s</tspan>', $textX, $status), $badgeSvg);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public static function dataPHPUnitTestsBadge(): iterable
    {
        yield 'passing' => [0, 0, 'passing', '121', '#34D058', '#28A745', '4'];
        yield 'failures' => [2, 0, 'failing', '114', '#D73A49', '#CB2431', '5'];
        yield 'errors' => [0, 1, 'failing', '114', '#D73A49', '#CB2431', '5'];
    }

    #[DataProvider('dataPHPUnitPassingBadge')]
    public function testGeneratePHPUnitPassingBadgeShowsThePassedTests(
        int $tests,
        int $failures,
        int $errors,
        int $skipped,
        string $text,
        string $textX,
        string $colorA,
        string $colorB,
    ): void {
        $tempDirectory = $this->createTempDirectory('aurora_junit_');
        $junitFilePath = $tempDirectory . '/junit.xml';
        $badgeFilePath = $tempDirectory . '/passing.svg';

        file_put_contents($junitFilePath, $this->junitXml($tests, $failures, $errors, $skipped));

        try {
            new AuroraPHPUnitCodeCoverageBadge()->generatePHPUnitPassingBadge($junitFilePath, $badgeFilePath);

            $badgeSvg = (string)file_get_contents($badgeFilePath);
            self::assertNotFalse(simplexml_load_string($badgeSvg));
            self::assertStringContainsString(sprintf('<tspan x="%s" y="14">%s</tspan>', $textX, $text), $badgeSvg);
            self::assertStringContainsString(sprintf('<tspan x="%s" y="15">%s</tspan>', $textX, $text), $badgeSvg);
            self::assertStringContainsString(sprintf('<stop stop-color="%s" offset="0%%"></stop>', $colorA), $badgeSvg);
            self::assertStringContainsString(sprintf('<stop stop-color="%s" offset="100%%"></stop>', $colorB), $badgeSvg);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public static function dataPHPUnitPassingBadge(): iterable
    {
        // The text is centered in the 89px wide right block, 6.5px per character
        yield 'skipped tests are not passed' => [10, 0, 0, 2, '8 / 10', '25', '#34D058', '#28A745'];
        yield 'failures and errors are red' => [10, 1, 1, 0, '8 / 10', '25', '#D73A49', '#CB2431'];
        yield 'longer text' => [120, 0, 0, 0, '120 / 120', '15.25', '#34D058', '#28A745'];
    }

    #[DataProvider('dataJUnitBadgeMethods')]
    public function testJUnitBadgesRequireAnExistingJUnitFile(string $method): void
    {
        $tempDirectory = $this->createTempDirectory('aurora_junit_');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid input file provided');

            new AuroraPHPUnitCodeCoverageBadge()->{$method}($tempDirectory . '/missing.xml', $tempDirectory . '/badge.svg');
        } finally {
            self::assertFileDoesNotExist($tempDirectory . '/badge.svg');
            $this->removeDirectory($tempDirectory);
        }
    }

    public static function dataJUnitBadgeMethods(): iterable
    {
        yield 'tests badge' => ['generatePHPUnitTestsBadge'];
        yield 'passing badge' => ['generatePHPUnitPassingBadge'];
    }

    public function testGenerateCoverageBadgesRequiresAnExistingCloverFile(): void
    {
        $tempDirectory = $this->createTempDirectory('aurora_badge_');
        $cloverFile    = $tempDirectory . '/missing-clover.xml';

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('Clover XML file (%s) does not exist', $cloverFile));

            new AuroraPHPUnitCodeCoverageBadge()->generateCoverageBadges($cloverFile, $tempDirectory . '/coverage.svg', $tempDirectory . '/statements.svg');
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageBadgesReportsZeroPercentForACloverWithoutElements(): void
    {
        $tempDirectory     = $this->createTempDirectory('aurora_badge_');
        $cloverFilePath    = $tempDirectory . '/clover.xml';
        $coverageSvgPath   = $tempDirectory . '/coverage.svg';
        $statementsSvgPath = $tempDirectory . '/statements.svg';

        file_put_contents($cloverFilePath, $this->cloverXml(0, 0, 0, 0));

        try {
            new AuroraPHPUnitCodeCoverageBadge()->generateCoverageBadges($cloverFilePath, $coverageSvgPath, $statementsSvgPath);

            $coverageSvg = (string)file_get_contents($coverageSvgPath);
            self::assertStringContainsString('<text x="80" y="14" fill="#000000">0%</text>', $coverageSvg);
            self::assertStringContainsString('<path fill="#E0E6EB" d="M63 0h36v20H63z"/>', $coverageSvg);

            self::assertStringContainsString('<text x="117" y="14" fill="#000000">0 / 0</text>', (string)file_get_contents($statementsSvgPath));
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testAppendCoverageHistoryStartsANewLineAfterAHistoryWithoutTrailingNewline(): void
    {
        $tempDirectory   = $this->createTempDirectory('aurora_history_');
        $cloverFilePath  = $tempDirectory . '/clover.xml';
        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';

        $firstLine = '{"date":"2026-05-01","sha":null,"coverage":50,"statements":100,"coveredStatements":50}';
        file_put_contents($historyFilePath, $firstLine);
        file_put_contents($cloverFilePath, $this->cloverXml(10, 7, 20, 14));

        try {
            self::assertTrue(new AuroraPHPUnitCodeCoverageBadge()->appendCoverageHistory($cloverFilePath, $historyFilePath, ' 0123456789abcdef ', '2026-05-02'));

            self::assertSame(
                $firstLine . "\n" . '{"date":"2026-05-02","sha":"0123456","coverage":70,"statements":20,"coveredStatements":14}' . "\n",
                file_get_contents($historyFilePath)
            );
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageTrendBadgeSkipsInvalidLinesAndDrawsASingleEntryAsAFlatLine(): void
    {
        $tempDirectory   = $this->createTempDirectory('aurora_trend_');
        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';
        $trendFilePath   = $tempDirectory . '/coverage-trend.svg';

        file_put_contents($historyFilePath, implode("\r\n", [
            'not json',
            '{"date":"2026-05-01","coverage":50}',
            '{"date":20260502,"coverage":60,"statements":10,"coveredStatements":6}',
            '{"date":"2026-05-03","coverage":"n/a","statements":10,"coveredStatements":6}',
            '',
            '{"date":"2026-06-01","sha":"","coverage":100,"statements":10,"coveredStatements":10}',
        ]));

        try {
            new AuroraPHPUnitCodeCoverageBadge()->generateCoverageTrendBadge($historyFilePath, $trendFilePath);

            $trendSvg = (string)file_get_contents($trendFilePath);

            // A flat 100% series is drawn in the 96-100 range: at the top of the chart, over the full width
            self::assertStringContainsString('points="8,24 292,24"', $trendSvg);
            self::assertStringContainsString('points="8,24 292,24 292,48 8,48"', $trendSvg);
            self::assertStringContainsString('fill="#44CC11">100% &#183; 10/10</text>', $trendSvg);
            self::assertStringContainsString('<text x="8" y="57">2026-06-01</text>', $trendSvg);
            self::assertStringNotContainsString('<text x="292" y="57"', $trendSvg);
            self::assertStringNotContainsString('2026-05-0', $trendSvg);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageTrendBadgeKeepsANearZeroSeriesAboveTheBottom(): void
    {
        $tempDirectory   = $this->createTempDirectory('aurora_trend_');
        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';
        $trendFilePath   = $tempDirectory . '/coverage-trend.svg';

        file_put_contents($historyFilePath, implode("\n", [
            '{"date":"2026-05-01","sha":null,"coverage":0,"statements":100,"coveredStatements":0}',
            '{"date":"2026-06-01","sha":null,"coverage":1,"statements":100,"coveredStatements":1}',
        ]));

        try {
            new AuroraPHPUnitCodeCoverageBadge()->generateCoverageTrendBadge($historyFilePath, $trendFilePath);

            $trendSvg = (string)file_get_contents($trendFilePath);

            // The 0-1 span is widened to 0-4 (not -1.5..2.5, below the chart)
            self::assertStringContainsString('points="8,48 292,42"', $trendSvg);
            self::assertStringContainsString('stroke="#E0E6EB"', $trendSvg);
            self::assertStringContainsString('<text x="292" y="57" text-anchor="end">2026-06-01</text>', $trendSvg);
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testGenerateCoverageTrendBadgeRequiresAValidHistoryEntry(): void
    {
        $tempDirectory   = $this->createTempDirectory('aurora_trend_');
        $historyFilePath = $tempDirectory . '/coverage-trend.ndjson';

        file_put_contents($historyFilePath, "not json\n{\"date\":\"2026-05-01\"}\n");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('Coverage history file (%s) does not exist or contains no valid entries.', $historyFilePath));

            new AuroraPHPUnitCodeCoverageBadge()->generateCoverageTrendBadge($historyFilePath, $tempDirectory . '/coverage-trend.svg');
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testBackfillCoverageHistoryFromGitFailsOutsideAGitRepository(): void
    {
        if (0 !== $this->runGit(['--version'], sys_get_temp_dir())[0]) {
            self::markTestSkipped('The "git" binary is not available.');
        }

        $tempDirectory = $this->createTempDirectory('aurora_backfill_');

        try {
            if (0 === $this->runGit(['rev-parse', '--is-inside-work-tree'], $tempDirectory)[0]) {
                self::markTestSkipped('The temporary directory is inside a git repository.');
            }

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(sprintf('Failed to read the git history of "%s/statements.svg": ', $tempDirectory));

            new AuroraPHPUnitCodeCoverageBadge()->backfillCoverageHistoryFromGit($tempDirectory . '/coverage-trend.ndjson');
        } finally {
            self::assertFileDoesNotExist($tempDirectory . '/coverage-trend.ndjson');
            $this->removeDirectory($tempDirectory);
        }
    }

    public function testBackfillCoverageHistoryFromGitSkipsUnreadableBadgesAndApproximatesAMissingCoverageBadge(): void
    {
        if (0 !== $this->runGit(['--version'], sys_get_temp_dir())[0]) {
            self::markTestSkipped('The "git" binary is not available.');
        }

        $tempDirectory   = $this->createTempDirectory('aurora_backfill_');
        $badgesDirectory = $tempDirectory . '/badges';
        $historyFilePath = $tempDirectory . '/history/coverage-trend.ndjson';
        $statementsPath  = $badgesDirectory . '/statements-badge.svg';
        $coveragePath    = $badgesDirectory . '/coverage-badge.svg';
        self::assertTrue(mkdir($badgesDirectory));
        self::assertTrue(mkdir(dirname($historyFilePath)));

        try {
            self::assertSame(0, $this->runGit(['init', '-q', '.'], $tempDirectory)[0]);

            // No coverage badge yet: the coverage is approximated from the statements ratio
            file_put_contents($statementsPath, $this->statementsSvg(3, 4));
            $this->gitCommit($tempDirectory, 'Statements badge only');

            // No "covered / total" value: the commit is skipped
            file_put_contents($statementsPath, '<svg xmlns="http://www.w3.org/2000/svg"><text>n/a</text></svg>');
            $this->gitCommit($tempDirectory, 'Unreadable statements badge');

            // No statement at all: 0%, not a division by zero
            file_put_contents($statementsPath, $this->statementsSvg(0, 0));
            $this->gitCommit($tempDirectory, 'Empty statements badge');

            // The coverage badge wins over the approximation (7 / 8 would be 87%)
            file_put_contents($statementsPath, $this->statementsSvg(7, 8));
            file_put_contents($coveragePath, $this->coverageSvg(90));
            $this->gitCommit($tempDirectory, 'Both badges');

            self::assertSame(3, new AuroraPHPUnitCodeCoverageBadge()->backfillCoverageHistoryFromGit($historyFilePath, $statementsPath, $coveragePath));

            $entries = array_map(
                static fn(string $line): array => array_intersect_key(json_decode($line, true), ['coverage' => 0, 'statements' => 0, 'coveredStatements' => 0]),
                array_values(array_filter(explode("\n", (string)file_get_contents($historyFilePath))))
            );

            self::assertSame(
                [
                    ['coverage' => 75, 'statements' => 4, 'coveredStatements' => 3],
                    ['coverage' => 0, 'statements' => 0, 'coveredStatements' => 0],
                    ['coverage' => 90, 'statements' => 8, 'coveredStatements' => 7],
                ],
                $entries
            );
        } finally {
            $this->removeDirectory($tempDirectory);
        }
    }

    private function createTempDirectory(string $prefix): string
    {
        $tempDirectory = sys_get_temp_dir() . '/' . $prefix . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        return $tempDirectory;
    }

    private function junitXml(int $tests, int $failures, int $errors, int $skipped): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
    <testsuite name="Project Test Suite" tests="{$tests}" assertions="{$tests}" errors="{$errors}" failures="{$failures}" skipped="{$skipped}" time="0.5"/>
</testsuites>
XML;
    }

    private function cloverXml(int $elements, int $coveredElements, int $statements, int $coveredStatements): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
    <project>
        <metrics elements="{$elements}" coveredelements="{$coveredElements}"/>
        <file name="Sample.php">
            <metrics statements="{$statements}" coveredstatements="{$coveredStatements}"/>
        </file>
    </project>
</coverage>
XML;
    }

    private function statementsSvg(int $coveredStatements, int $statements): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="20">
    <text x="117" y="14">{$coveredStatements} / {$statements}</text>
</svg>
SVG;
    }

    private function coverageSvg(int $coverage): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="99" height="20">
    <text x="80" y="14">{$coverage}%</text>
</svg>
SVG;
    }

    private function gitCommit(string $workingDirectory, string $message): void
    {
        self::assertSame(0, $this->runGit(['add', '.'], $workingDirectory)[0]);
        self::assertSame(0, $this->runGit([
            '-c', 'user.name=Aurora Tests',
            '-c', 'user.email=aurora-tests@example.com',
            '-c', 'commit.gpgsign=false',
            'commit', '-q', '-m', $message,
        ], $workingDirectory)[0]);
    }

    /**
     * @param string[] $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runGit(array $arguments, string $workingDirectory): array
    {
        $process = @proc_open(
            array_merge(['git'], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory
        );

        if (!is_resource($process)) {
            return [1, ''];
        }

        $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
