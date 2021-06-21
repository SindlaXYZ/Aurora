<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Diacritics;

use Sindla\Bundle\AuroraBundle\Utils\Diacritics\Extension\Romanian;

/**
 * EXPERIMENTAL
 */
class Diacritics
{
    private array $sets = [];

    public function useRomanian()
    {
        $this->sets = Romanian::sets();
    }

    public function inject(array $collection): void
    {
        $this->sets = array_merge($this->sets, $collection);
    }

    public function modify(string $string): string
    {
        $string = str_replace(['Ş', 'ş', 'Ţ', 'ţ'], ['Ș', 'ș', 'Ț', 'ț'], $string);

        foreach ($this->sets as $reg => $newValue) {
            $string = preg_replace('/' . $reg . '/', $newValue, $string);
        }

        return $string;
    }
}