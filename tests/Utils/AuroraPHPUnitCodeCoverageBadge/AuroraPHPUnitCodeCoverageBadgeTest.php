<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPHPUnitCodeCoverageBadge;

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
