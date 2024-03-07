<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadge
{
    public function generate(string $cloverXMLFilePath, string $outputSVGFilePath): void
    {
        if (!file_exists($cloverXMLFilePath)) {
            throw new \InvalidArgumentException('Invalid input file provided');
        }

        $xml             = new \SimpleXMLElement(file_get_contents($cloverXMLFilePath));
        $metrics         = $xml->xpath('//metrics');
        $totalElements   = 0;
        $checkedElements = 0;

        foreach ($metrics as $metric) {
            $totalElements   += (int)$metric['elements'];
            $checkedElements += (int)$metric['coveredelements'];
        }

        $coverage = (int)(($totalElements === 0) ? 0 : ($checkedElements / $totalElements) * 100);
        $template = $this->_flatSVGTemplate();
        $template = str_replace('{{ total }}', $coverage, $template);

        $color = '#a4a61d';      // Default Gray
        if ($coverage < 40) {
            $color = '#D73A49';  // Red
        } else if ($coverage < 60) {
            $color = '#fe7d37';  // Orange
        } else if ($coverage < 75) {
            $color = '#dfb317';  // Yellow
        } else if ($coverage < 90) {
            $color = '#a4a61d';  // Yellow-Green
        } else if ($coverage < 95) {
            $color = '#97CA00';  // Green
        } else if ($coverage <= 100) {
            $color = '#4c1';     // Bright Green
        }

        $template = str_replace('{{ total }}', $coverage, $template);
        $template = str_replace('{{ color }}', $color, $template);

        file_put_contents($outputSVGFilePath, $template);
    }

    private function _flatSVGTemplate(): string
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
        <text x="31.5" y="15" fill="#010101" fill-opacity=".3">coverage</text>
        <text x="31.5" y="14">coverage</text>
        <text x="80" y="15" fill="#010101" fill-opacity=".3">{{ total }}%</text>
        <text x="80" y="14">{{ total }}%</text>
    </g>
</svg>
SVG;
    }

}
