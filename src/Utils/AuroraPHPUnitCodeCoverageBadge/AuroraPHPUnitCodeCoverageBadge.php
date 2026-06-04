<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadge
{
    public function generatePHPUnitTestsBadge(string $junitXMLFilePath, string $outputSVGFilePath): void
    {
        if (!file_exists($junitXMLFilePath)) {
            throw new \InvalidArgumentException('Invalid input file provided');
        }

        $xml      = simplexml_load_file($junitXMLFilePath);
        $failures = (int)$xml->testsuite['failures'];
        $errors   = (int)$xml->testsuite['errors'];

        $isPassing = ($failures === 0 && $errors === 0);

        file_put_contents($outputSVGFilePath, $this->_PHPUnitTestsBadge($isPassing));
    }

    public function generatePHPUnitPassingBadge(string $junitXMLFilePath, string $outputSVGFilePath): void
    {
        if (!file_exists($junitXMLFilePath)) {
            throw new \InvalidArgumentException('Invalid input file provided');
        }

        $xml         = simplexml_load_file($junitXMLFilePath);
        $testsTotal  = (int)$xml->testsuite['tests'];
        $failures    = (int)$xml->testsuite['failures'];
        $errors      = (int)$xml->testsuite['errors'];
        $skipped     = (int)$xml->testsuite['skipped'];
        $passedTests = $testsTotal - $failures - $errors - $skipped;

        // All test cases passed
        if ($failures === 0 && $errors === 0) {
            $colorA = '#34D058';  // Bright Green
            $colorB = '#28A745';  // Bright Green
        } else {
            $colorA = '#D73A49';  // Red
            $colorB = '#CB2431';  // Red
        }

        $PHPUnitSVG = $this->_PHPUnitPassingBadge($passedTests, $testsTotal, $colorA, $colorB);
        file_put_contents($outputSVGFilePath, $PHPUnitSVG);
    }

    /**
     * @throws \Exception
     */
    public function generateCoverageBadges(string $cloverXMLFilePath, string $outputCoverageSVGFilePath, string $outputStatementsSVGFilePath): void
    {
        ['coverage' => $coverage, 'statements' => $statements, 'coveredStatements' => $coveredStatements] = $this->_parseCloverFile($cloverXMLFilePath);

        [$background, $textColor] = $this->_coverageColors($coverage);

        $coverageSVG = $this->_coverageSVG();
        $coverageSVG = str_replace('{{ background }}', $background, $coverageSVG);
        $coverageSVG = str_replace('{{ textColor }}', $textColor, $coverageSVG);
        $coverageSVG = str_replace('{{ total }}', (string)$coverage, $coverageSVG);
        file_put_contents($outputCoverageSVGFilePath, $coverageSVG);

        $statementsSVG = $this->__statementsSVG();
        $statementsSVG = str_replace('{{ background }}', $background, $statementsSVG);
        $statementsSVG = str_replace('{{ textColor }}', $textColor, $statementsSVG);
        $statementsSVG = str_replace('{{ statements }}', (string)$statements, $statementsSVG);
        $statementsSVG = str_replace('{{ coveredStatements }}', (string)$coveredStatements, $statementsSVG);
        file_put_contents($outputStatementsSVGFilePath, $statementsSVG);
    }

    /**
     * Append the current clover.xml metrics as a new line to the coverage history NDJSON file.
     *
     * Each line is a JSON object: {"date": "...", "sha": "...", "coverage": int, "statements": int, "coveredStatements": int}.
     * Consecutive identical entries are skipped: when the last recorded entry carries the same coverage / statements /
     * coveredStatements values, the file is left untouched.
     *
     * Returns true when a new entry was appended, false when it was skipped as a duplicate of the last entry.
     *
     * @throws \Exception
     */
    public function appendCoverageHistory(string $cloverXMLFilePath, string $historyNDJSONFilePath, ?string $commitSha = null, ?string $date = null): bool
    {
        $metrics = $this->_parseCloverFile($cloverXMLFilePath);
        $entries = $this->_readCoverageHistory($historyNDJSONFilePath);
        $last    = ([] === $entries) ? null : $entries[array_key_last($entries)];

        if (
            null !== $last
            && $last['coverage'] === $metrics['coverage']
            && $last['statements'] === $metrics['statements']
            && $last['coveredStatements'] === $metrics['coveredStatements']
        ) {
            return false;
        }

        $entry = [
            'date'              => $date ?? gmdate('Y-m-d'),
            'sha'               => (null !== $commitSha && '' !== trim($commitSha)) ? substr(trim($commitSha), 0, 7) : null,
            'coverage'          => $metrics['coverage'],
            'statements'        => $metrics['statements'],
            'coveredStatements' => $metrics['coveredStatements'],
        ];

        // Guard against a history file that does not end with a newline (manual edits)
        $prefix = '';
        if (file_exists($historyNDJSONFilePath)) {
            $existingContent = (string)file_get_contents($historyNDJSONFilePath);
            if ('' !== $existingContent && !str_ends_with($existingContent, "\n")) {
                $prefix = "\n";
            }
        }

        file_put_contents($historyNDJSONFilePath, $prefix . $this->_encodeCoverageHistoryEntry($entry) . "\n", FILE_APPEND | LOCK_EX);

        return true;
    }

    /**
     * Rebuild the coverage history NDJSON file retroactively, from the git history of the statements / coverage SVG badge files.
     *
     * Walks every commit that touched the statements SVG badge, extracts the historical values out of the committed SVG blobs
     * ("coveredStatements / statements" from the statements badge, "coverage%" from the coverage badge) and rewrites the whole
     * NDJSON file (any existing file content is replaced). Consecutive entries with identical values are collapsed into one.
     * When the statements / coverage SVG file paths are not provided, they default to "statements.svg" / "coverage.svg"
     * located in the same directory as the NDJSON file.
     *
     * Returns the number of entries written.
     */
    public function backfillCoverageHistoryFromGit(string $historyNDJSONFilePath, ?string $statementsSVGFilePath = null, ?string $coverageSVGFilePath = null): int
    {
        $badgesDirectory       = \dirname($historyNDJSONFilePath);
        $statementsSVGFilePath ??= $badgesDirectory . '/statements.svg';
        $coverageSVGFilePath   ??= $badgesDirectory . '/coverage.svg';

        $statementsDirectory = \dirname($statementsSVGFilePath);
        $statementsBasename  = basename($statementsSVGFilePath);
        $coverageDirectory   = \dirname($coverageSVGFilePath);
        $coverageBasename    = basename($coverageSVGFilePath);

        // "./<file>" pathspecs are relative to the git working directory, so no repository-root resolution is needed
        [$exitCode, $logLines, $errorOutput] = $this->_git($statementsDirectory, ['log', '--reverse', '--format=%H %cs', '--', './' . $statementsBasename]);

        if (0 !== $exitCode) {
            throw new \RuntimeException(sprintf('Failed to read the git history of "%s": %s.', $statementsSVGFilePath, $errorOutput));
        }

        $entries = [];

        foreach ($logLines as $logLine) {
            $parts = explode(' ', trim($logLine), 2);
            if (2 !== count($parts)) {
                continue;
            }

            [$sha, $date] = $parts;

            [$showExitCode, $showLines] = $this->_git($statementsDirectory, ['show', sprintf('%s:./%s', $sha, $statementsBasename)]);

            if (0 !== $showExitCode || !preg_match('/>(\d+) \/ (\d+)</', implode("\n", $showLines), $statementsMatches)) {
                continue;
            }

            $coveredStatements = (int)$statementsMatches[1];
            $statements        = (int)$statementsMatches[2];
            $coverage          = null;

            [$coverageExitCode, $coverageLines] = $this->_git($coverageDirectory, ['show', sprintf('%s:./%s', $sha, $coverageBasename)]);

            if (0 === $coverageExitCode && preg_match('/>(\d+)%</', implode("\n", $coverageLines), $coverageMatches)) {
                $coverage = (int)$coverageMatches[1];
            }

            if (null === $coverage) {
                // The coverage SVG badge is missing for this commit; approximate from the statements ratio
                $coverage = (int)((0 === $statements) ? 0 : ($coveredStatements / $statements) * 100);
            }

            $entries[] = [
                'date'              => $date,
                'sha'               => substr($sha, 0, 7),
                'coverage'          => $coverage,
                'statements'        => $statements,
                'coveredStatements' => $coveredStatements,
            ];
        }

        $entries = $this->_deduplicateConsecutiveCoverageEntries($entries);

        $ndjson = '';
        foreach ($entries as $entry) {
            $ndjson .= $this->_encodeCoverageHistoryEntry($entry) . "\n";
        }

        file_put_contents($historyNDJSONFilePath, $ndjson, LOCK_EX);

        return count($entries);
    }

    /**
     * Generate a self-contained SVG sparkline badge with the coverage evolution, from the coverage history NDJSON file.
     */
    public function generateCoverageTrendBadge(string $historyNDJSONFilePath, string $outputTrendSVGFilePath): void
    {
        $entries = $this->_readCoverageHistory($historyNDJSONFilePath);

        if ([] === $entries) {
            throw new \InvalidArgumentException(sprintf('Coverage history file (%s) does not exist or contains no valid entries.', $historyNDJSONFilePath));
        }

        file_put_contents($outputTrendSVGFilePath, $this->_coverageTrendSVG($entries));
    }

    private function _PHPUnitTestsBadge(bool $isPassing): string
    {
        // Exact dimensions and paths sourced from GitHub's badge generator output.
        if ($isPassing) {
            $title      = 'PHPUnit - passing';
            $colorA     = '#34D058';
            $colorB     = '#28A745';
            $totalWidth = 121;
            $rightPath  = 'M0 0h46.939C48.629 0 50 1.343 50 3v14c0 1.657-1.37 3-3.061 3H0V0z';
            $textX      = 4;
            $status     = 'passing';
        } else {
            $title      = 'PHPUnit - failing';
            $colorA     = '#D73A49';
            $colorB     = '#CB2431';
            $totalWidth = 114;
            $rightPath  = 'M0 0h40.47C41.869 0 43 1.343 43 3v14c0 1.657-1.132 3-2.53 3H0V0z';
            $textX      = 5;
            $status     = 'failing';
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$totalWidth}" height="20">
  <title>{$title}</title>
  <defs>
    <linearGradient id="workflow-fill" x1="50%" y1="0%" x2="50%" y2="100%">
      <stop stop-color="#444D56" offset="0%"></stop>
      <stop stop-color="#24292E" offset="100%"></stop>
    </linearGradient>
    <linearGradient id="state-fill" x1="50%" y1="0%" x2="50%" y2="100%">
      <stop stop-color="{$colorA}" offset="0%"></stop>
      <stop stop-color="{$colorB}" offset="100%"></stop>
    </linearGradient>
  </defs>
  <g fill="none" fill-rule="evenodd">
    <g font-family="&#39;DejaVu Sans&#39;,Verdana,Geneva,sans-serif" font-size="11">
      <path id="workflow-bg" d="M0,3 C0,1.3431 1.3552,0 3.02702703,0 L71,0 L71,20 L3.02702703,20 C1.3552,20 0,18.6569 0,17 L0,3 Z" fill="url(#workflow-fill)" fill-rule="nonzero"></path>
      <text fill="#010101" fill-opacity=".3">
        <tspan x="22.1981982" y="15" aria-hidden="true">PHPUnit</tspan>
      </text>
      <text fill="#FFFFFF">
        <tspan x="22.1981982" y="14">PHPUnit</tspan>
      </text>
    </g>
    <g transform="translate(71)" font-family="&#39;DejaVu Sans&#39;,Verdana,Geneva,sans-serif" font-size="11">
      <path d="{$rightPath}" id="state-bg" fill="url(#state-fill)" fill-rule="nonzero"></path>
      <text fill="#010101" fill-opacity=".3" aria-hidden="true">
        <tspan x="{$textX}" y="15">{$status}</tspan>
      </text>
      <text fill="#FFFFFF">
        <tspan x="{$textX}" y="14">{$status}</tspan>
      </text>
    </g>
    <path fill="#959DA5" d="M11 3c-3.868 0-7 3.132-7 7a6.996 6.996 0 0 0 4.786 6.641c.35.062.482-.148.482-.332 0-.166-.01-.718-.01-1.304-1.758.324-2.213-.429-2.353-.822-.079-.202-.42-.823-.717-.99-.245-.13-.595-.454-.01-.463.552-.009.946.508 1.077.718.63 1.058 1.636.76 2.039.577.061-.455.245-.761.446-.936-1.557-.175-3.185-.779-3.185-3.456 0-.762.271-1.392.718-1.882-.07-.175-.315-.892.07-1.855 0 0 .586-.183 1.925.718a6.5 6.5 0 0 1 1.75-.236 6.5 6.5 0 0 1 1.75.236c1.338-.91 1.925-.718 1.925-.718.385.963.14 1.68.07 1.855.446.49.717 1.112.717 1.882 0 2.686-1.636 3.28-3.194 3.456.254.219.473.639.473 1.295 0 .936-.009 1.689-.009 1.925 0 .184.131.402.481.332A7.011 7.011 0 0 0 18 10c0-3.867-3.133-7-7-7z"></path>
  </g>
</svg>
SVG;
    }

    private function _PHPUnitPassingBadge(int $passedTests, int $totalTests, string $colorA, string $colorB): string
    {
        $text            = "{$passedTests} / {$totalTests}";
        $textWidth       = strlen($text) * 6.5; // 6.5 is the average character width
        $backgroundWidth = 89;
        $centeredX       = ($backgroundWidth - $textWidth) / 2;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="160" height="20">
    <defs>
        <linearGradient id="workflow-fill" x1="50%" y1="0%" x2="50%" y2="100%">
            <stop stop-color="#444D56" offset="0%"></stop>
            <stop stop-color="#24292E" offset="100%"></stop>
        </linearGradient>
        <linearGradient id="state-fill" x1="50%" y1="0%" x2="50%" y2="100%">
            <stop stop-color="{$colorA}" offset="0%"></stop>
            <stop stop-color="{$colorB}" offset="100%"></stop>
        </linearGradient>
    </defs>
    <g fill="none" fill-rule="evenodd">
        <g font-family="'DejaVu Sans',Verdana,Geneva,sans-serif" font-size="11">
            <path id="workflow-bg" d="M0,3 C0,1.3431 1.3552,0 3.02702703,0 L71,0 L71,20 L3.02702703,20 C1.3552,20 0,18.6569 0,17 L0,3 Z" fill="url(#workflow-fill)" fill-rule="nonzero"></path>
            <text fill="#010101" fill-opacity=".3">
                <tspan x="22.1981982" y="15" aria-hidden="true">PHPUnit</tspan>
            </text>
            <text fill="#FFFFFF">
                <tspan x="22.1981982" y="14">PHPUnit</tspan>
            </text>
        </g>
        <g transform="translate(71)" font-family="'DejaVu Sans',Verdana,Geneva,sans-serif" font-size="11">
            <path d="M0 0h85.939C87.629 0 89 1.343 89 3v14c0 1.657-1.37 3-3.061 3H0V0z" id="state-bg" fill="url(#state-fill)" fill-rule="nonzero"></path>
            <text fill="#010101" fill-opacity=".3" aria-hidden="true">
                <tspan x="{$centeredX}" y="15">{$text}</tspan>
            </text>
            <text fill="#FFFFFF">
                <tspan x="{$centeredX}" y="14">{$text}</tspan>
            </text>
        </g>
        <path fill="#959DA5" d="M11 3c-3.868 0-7 3.132-7 7a6.996 6.996 0 0 0 4.786 6.641c.35.062.482-.148.482-.332 0-.166-.01-.718-.01-1.304-1.758.324-2.213-.429-2.353-.822-.079-.202-.42-.823-.717-.99-.245-.13-.595-.454-.01-.463.552-.009.946.508 1.077.718.63 1.058 1.636.76 2.039.577.061-.455.245-.761.446-.936-1.557-.175-3.185-.779-3.185-3.456 0-.762.271-1.392.718-1.882-.07-.175-.315-.892.07-1.855 0 0 .586-.183 1.925.718a6.5 6.5 0 0 1 1.75-.236 6.5 6.5 0 0 1 1.75.236c1.338-.91 1.925-.718 1.925-.718.385.963.14 1.68.07 1.855.446.49.717 1.112.717 1.882 0 2.686-1.636 3.28-3.194 3.456.254.219.473.639.473 1.295 0 .936-.009 1.689-.009 1.925 0 .184.131.402.481.332A7.011 7.011 0 0 0 18 10c0-3.867-3.133-7-7-7z"></path>
    </g>
</svg>
SVG;
    }

    private function _coverageSVG(): string
    {
        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="99" height="20">
    <linearGradient id="workflow-fill" x1="50%" y1="0%" x2="50%" y2="100%">
        <stop stop-color="#444D56" offset="0%"></stop>
        <stop stop-color="#24292E" offset="100%"></stop>
    </linearGradient>
    <linearGradient id="b" x2="0" y2="100%">
        <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
        <stop offset="1" stop-opacity=".1"/>
    </linearGradient>
    <mask id="a">
        <rect width="99" height="20" rx="3" fill="#fff"/>
    </mask>
    <g mask="url(#a)">
        <path fill="url(#workflow-fill)" d="M0 0h63v20H0z"/>
        <path fill="{{ background }}" d="M63 0h36v20H63z"/>
        <path fill="url(#b)" d="M0 0h99v20H0z"/>
    </g>
    <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
        <text x="31.5" y="15" fill="#010101" fill-opacity=".3">Coverage</text>
        <text x="31.5" y="14">Coverage</text>
        <text x="80" y="15" fill="#010101" fill-opacity=".3">{{ total }}%</text>
        <text x="80" y="14" fill="{{ textColor }}">{{ total }}%</text>
    </g>
</svg>
SVG;
    }

    private function __statementsSVG(): string
    {
        $width           = 160;
        $leftBlock       = 75;
        $rightBlockWidth = $width - $leftBlock;

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="20">
    <linearGradient id="workflow-fill" x1="50%" y1="0%" x2="50%" y2="100%">
        <stop stop-color="#444D56" offset="0%"></stop>
        <stop stop-color="#24292E" offset="100%"></stop>
    </linearGradient>
    <linearGradient id="b" x2="0" y2="100%">
        <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
        <stop offset="1" stop-opacity=".1"/>
    </linearGradient>
    <mask id="a">
        <rect width="{$width}" height="20" rx="3" fill="#fff"/>
    </mask>
    <g mask="url(#a)">
        <path fill="url(#workflow-fill)" d="M0 0h{$leftBlock}v20H0z"/>
        <path fill="{{ background }}" d="M{$leftBlock} 0h{$rightBlockWidth}v20H{$leftBlock}z"/>
        <path fill="url(#b)" d="M0 0h{$width}v20H0z"/>
    </g>
    <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
        <text x="38" y="15" fill="#010101" fill-opacity=".3">Statements</text>
        <text x="38" y="14">Statements</text>
        <text x="117" y="15" fill="#010101" fill-opacity=".3">{{ coveredStatements }} / {{ statements }}</text>
        <text x="117" y="14" fill="{{ textColor }}">{{ coveredStatements }} / {{ statements }}</text>
    </g>
</svg>
SVG;
    }

    /**
     * Extract the aggregated coverage metrics out of a clover.xml file.
     *
     * @return array{coverage: int, statements: int, coveredStatements: int}
     *
     * @throws \Exception
     */
    private function _parseCloverFile(string $cloverXMLFilePath): array
    {
        if (!file_exists($cloverXMLFilePath)) {
            throw new \InvalidArgumentException(sprintf('Clover XML file (%s) does not exist', $cloverXMLFilePath));
        }

        $xml             = new \SimpleXMLElement(file_get_contents($cloverXMLFilePath));
        $metrics         = $xml->xpath('//metrics');
        $files           = $xml->xpath('//file');
        $totalElements   = 0;
        $checkedElements = 0;

        foreach ($metrics as $metric) {
            $totalElements   += (int)$metric['elements'];
            $checkedElements += (int)$metric['coveredelements'];
        }

        $statements        = 0;
        $coveredStatements = 0;
        foreach ($files as $file) {
            $statements        += (int)$file->metrics['statements'];
            $coveredStatements += (int)$file->metrics['coveredstatements'];
        }

        return [
            'coverage'          => (int)(($totalElements === 0) ? 0 : ($checkedElements / $totalElements) * 100),
            'statements'        => $statements,
            'coveredStatements' => $coveredStatements,
        ];
    }

    /**
     * Map a coverage percentage to the badge background / text colors.
     *
     * @return array{0: string, 1: string} [background, textColor]
     */
    private function _coverageColors(int $coverage): array
    {
        // [minCoverage, background, textColor] — ordered from high to low
        $scale = [
            [92, '#44CC11', '#FFFFFF'],
            [83, '#68CB0A', '#FFFFFF'],
            [75, '#7FCB05', '#FFFFFF'],
            [67, '#96CA00', '#FFFFFF'],
            [58, '#9EB50C', '#FFFFFF'],
            [50, '#AEAF11', '#FFFFFF'],
            [42, '#BEAA16', '#FFFFFF'],
            [33, '#CEA41C', '#FFFFFF'],
            [25, '#DE9F21', '#FFFFFF'],
            [17, '#EE9926', '#FFFFFF'],
            [8, '#FB8234', '#FFFFFF'],
        ];

        [$background, $textColor] = ['#E0E6EB', '#000000']; // grey fallback (< 8%)
        foreach ($scale as [$min, $bg, $fg]) {
            if ($coverage >= $min) {
                [$background, $textColor] = [$bg, $fg];
                break;
            }
        }

        return [$background, $textColor];
    }

    /**
     * Read and validate the coverage history NDJSON file; invalid lines are skipped silently.
     *
     * @return array<int, array{date: string, sha: string|null, coverage: int, statements: int, coveredStatements: int}>
     */
    private function _readCoverageHistory(string $historyNDJSONFilePath): array
    {
        if (!file_exists($historyNDJSONFilePath)) {
            return [];
        }

        $entries = [];
        $lines   = preg_split('/\r\n|\r|\n/', (string)file_get_contents($historyNDJSONFilePath)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            $entry = json_decode($line, true);

            if (
                !is_array($entry)
                || !isset($entry['date']) || !is_string($entry['date'])
                || !isset($entry['coverage']) || !is_numeric($entry['coverage'])
                || !isset($entry['statements']) || !is_numeric($entry['statements'])
                || !isset($entry['coveredStatements']) || !is_numeric($entry['coveredStatements'])
            ) {
                continue;
            }

            $entries[] = [
                'date'              => $entry['date'],
                'sha'               => (isset($entry['sha']) && is_string($entry['sha']) && '' !== $entry['sha']) ? $entry['sha'] : null,
                'coverage'          => (int)$entry['coverage'],
                'statements'        => (int)$entry['statements'],
                'coveredStatements' => (int)$entry['coveredStatements'],
            ];
        }

        return $entries;
    }

    /**
     * Collapse consecutive entries carrying identical coverage / statements / coveredStatements values.
     *
     * @param array<int, array{date: string, sha: string|null, coverage: int, statements: int, coveredStatements: int}> $entries
     *
     * @return array<int, array{date: string, sha: string|null, coverage: int, statements: int, coveredStatements: int}>
     */
    private function _deduplicateConsecutiveCoverageEntries(array $entries): array
    {
        $deduplicated = [];

        foreach ($entries as $entry) {
            $last = ([] === $deduplicated) ? null : $deduplicated[array_key_last($deduplicated)];

            if (
                null !== $last
                && $last['coverage'] === $entry['coverage']
                && $last['statements'] === $entry['statements']
                && $last['coveredStatements'] === $entry['coveredStatements']
            ) {
                continue;
            }

            $deduplicated[] = $entry;
        }

        return $deduplicated;
    }

    /**
     * Encode a coverage history entry as a single NDJSON line (without the trailing newline), with a stable key order.
     *
     * @param array{date: string, sha: string|null, coverage: int, statements: int, coveredStatements: int} $entry
     */
    private function _encodeCoverageHistoryEntry(array $entry): string
    {
        return json_encode([
            'date'              => $entry['date'],
            'sha'               => $entry['sha'],
            'coverage'          => $entry['coverage'],
            'statements'        => $entry['statements'],
            'coveredStatements' => $entry['coveredStatements'],
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Run a git command in the given working directory, without going through a shell.
     *
     * @param string[] $arguments
     *
     * @return array{0: int, 1: string[], 2: string} [exitCode, stdoutLines, stderr]
     */
    private function _git(string $workingDirectory, array $arguments): array
    {
        $process = @proc_open(
            array_merge(['git'], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory
        );

        if (!is_resource($process)) {
            return [1, [], sprintf('Could not start the "git" process in "%s".', $workingDirectory)];
        }

        $standardOutput = (string)stream_get_contents($pipes[1]);
        $standardError  = (string)stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $lines    = ('' === trim($standardOutput)) ? [] : (preg_split('/\r\n|\r|\n/', trim($standardOutput)) ?: []);

        return [$exitCode, $lines, trim($standardError)];
    }

    /**
     * @param array<int, array{date: string, sha: string|null, coverage: int, statements: int, coveredStatements: int}> $entries
     */
    private function _coverageTrendSVG(array $entries): string
    {
        $chartLeft   = 8;
        $chartRight  = 292;
        $chartTop    = 24;
        $chartBottom = 48;

        $series   = array_column($entries, 'coverage');
        $minValue = min($series);
        $maxValue = max($series);

        // Keep a minimum vertical span so a near-flat series does not degenerate into a 0-height chart
        if (($maxValue - $minValue) < 4) {
            $middle   = ($maxValue + $minValue) / 2;
            $minValue = $middle - 2;
            $maxValue = $middle + 2;

            if ($minValue < 0) {
                $minValue = 0;
                $maxValue = 4;
            }

            if ($maxValue > 100) {
                $maxValue = 100;
                $minValue = 96;
            }
        }

        // A single entry is rendered as a flat, full-width line
        if (1 === count($series)) {
            $series[] = $series[0];
        }

        $pointsCount = count($series);
        $linePoints  = [];

        foreach ($series as $index => $value) {
            $x            = $chartLeft + ($index * ($chartRight - $chartLeft) / ($pointsCount - 1));
            $y            = $chartBottom - (($value - $minValue) / ($maxValue - $minValue)) * ($chartBottom - $chartTop);
            $linePoints[] = round($x, 2) . ',' . round($y, 2);
        }

        $polylinePoints = implode(' ', $linePoints);
        $polygonPoints  = sprintf('%s %d,%d %d,%d', $polylinePoints, $chartRight, $chartBottom, $chartLeft, $chartBottom);

        $lastEntry     = $entries[array_key_last($entries)];
        [$accentColor] = $this->_coverageColors($lastEntry['coverage']);
        $label         = sprintf('%d%% &#183; %d/%d', $lastEntry['coverage'], $lastEntry['coveredStatements'], $lastEntry['statements']);

        $firstDate = $entries[0]['date'];
        $lastDate  = $lastEntry['date'];
        $dates     = sprintf('<text x="8" y="57">%s</text>', $firstDate);

        if ($lastDate !== $firstDate) {
            $dates .= "\n        " . sprintf('<text x="292" y="57" text-anchor="end">%s</text>', $lastDate);
        }

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="60">
    <linearGradient id="workflow-fill" x1="50%" y1="0%" x2="50%" y2="100%">
        <stop stop-color="#444D56" offset="0%"></stop>
        <stop stop-color="#24292E" offset="100%"></stop>
    </linearGradient>
    <mask id="a">
        <rect width="300" height="60" rx="3" fill="#fff"/>
    </mask>
    <g mask="url(#a)">
        <path fill="url(#workflow-fill)" d="M0 0h300v60H0z"/>
        <polygon fill="{$accentColor}" fill-opacity=".2" points="{$polygonPoints}"/>
        <polyline fill="none" stroke="{$accentColor}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="{$polylinePoints}"/>
    </g>
    <g font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
        <text x="8" y="17" fill="#010101" fill-opacity=".3">Coverage</text>
        <text x="8" y="16" fill="#FFFFFF">Coverage</text>
        <text x="292" y="17" text-anchor="end" fill="#010101" fill-opacity=".3">{$label}</text>
        <text x="292" y="16" text-anchor="end" fill="{$accentColor}">{$label}</text>
    </g>
    <g font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="9" fill="#959DA5">
        {$dates}
    </g>
</svg>
SVG;
    }
}
