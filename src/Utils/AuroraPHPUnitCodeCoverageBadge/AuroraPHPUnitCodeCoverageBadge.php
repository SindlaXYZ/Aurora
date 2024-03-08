<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadge
{
    public function generate(string $cloverXMLFilePath, string $outputCoverageSVGFilePath, string $outputStatementsSVGFilePath): void
    {
        if (!file_exists($cloverXMLFilePath)) {
            throw new \InvalidArgumentException('Invalid input file provided');
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
        $width     = 160;
        $leftBlock = 75;

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
        <path fill="{{ color }}" d="M{$leftBlock} 0h{$width}v20H{$leftBlock}z"/>
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
