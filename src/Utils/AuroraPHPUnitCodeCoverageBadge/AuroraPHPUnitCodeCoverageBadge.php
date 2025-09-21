<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadge
{
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

        $coverage = (int)(($totalElements === 0) ? 0 : ($checkedElements / $totalElements) * 100);

        if ($coverage >= 98) {
            $color = '#44CC11';  // Bright Green
        } else if ($coverage >= 90) {
            $color = '#97CA00';  // Green
        } else if ($coverage >= 75) {
            $color = '#A8961F';  // Yellow-Green
        } else if ($coverage >= 50) {
            $color = '#DFB317';  // Yellow
        } else if ($coverage >= 15) {
            $color = '#FE7D37';  // Orange
        } else {
            $color = '#D73A49';  // Red
        }

        $coverageSVG = $this->_coverageSVG();
        $coverageSVG = str_replace('{{ color }}', $color, $coverageSVG);
        $coverageSVG = str_replace('{{ total }}', $coverage, $coverageSVG);
        file_put_contents($outputCoverageSVGFilePath, $coverageSVG);

        $statementsSVG = $this->__statementsSVG();
        $statementsSVG = str_replace('{{ color }}', $color, $statementsSVG);
        $statementsSVG = str_replace('{{ statements }}', $statements, $statementsSVG);
        $statementsSVG = str_replace('{{ coveredStatements }}', $coveredStatements, $statementsSVG);
        file_put_contents($outputStatementsSVGFilePath, $statementsSVG);
    }

    private function _PHPUnitPassingBadge($passedTests, $totalTests, $colorA, $colorB): string
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
    <linearGradient id="b" x2="0" y2="100%">
        <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
        <stop offset="1" stop-opacity=".1"/>
    </linearGradient>
    <mask id="a">
        <rect width="99" height="20" rx="3" fill="#fff"/>
    </mask>
    <g mask="url(#a)">
        <path fill="#555" d="M0 0h63v20H0z"/>
        <path fill="{{ color }}" d="M63 0h36v20H63z"/>
        <path fill="url(#b)" d="M0 0h99v20H0z"/>
    </g>
    <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
        <text x="31.5" y="15" fill="#010101" fill-opacity=".3">Coverage</text>
        <text x="31.5" y="14">Coverage</text>
        <text x="80" y="15" fill="#010101" fill-opacity=".3">{{ total }}%</text>
        <text x="80" y="14">{{ total }}%</text>
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
    <linearGradient id="b" x2="0" y2="100%">
        <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
        <stop offset="1" stop-opacity=".1"/>
    </linearGradient>
    <mask id="a">
        <rect width="{$width}" height="20" rx="3" fill="#fff"/>
    </mask>
    <g mask="url(#a)">
        <path fill="#555" d="M0 0h{$leftBlock}v20H0z"/>
        <path fill="{{ color }}" d="M{$leftBlock} 0h{$rightBlockWidth}v20H{$leftBlock}z"/>
        <path fill="url(#b)" d="M0 0h{$width}v20H0z"/>
    </g>
    <g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="11">
        <text x="38" y="15" fill="#010101" fill-opacity=".3">Statements</text>
        <text x="38" y="14">Statements</text>
        <text x="117" y="15" fill="#010101" fill-opacity=".3">{{ coveredStatements }} / {{ statements }}</text>
        <text x="117" y="14">{{ coveredStatements }} / {{ statements }}</text>
    </g>
</svg>
SVG;
    }
}
