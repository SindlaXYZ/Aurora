<?php

namespace Sindla\Bundle\BorealisBundle\Utils\Diacritics;

use Sindla\Bundle\BorealisBundle\Utils\Diacritics\Extension\Romanian;

/**
 * EXPERIMENTAL
 */
class Diacritics
{
    private $sets = [];

    public function useRomanian()
    {
        $this->sets = Romanian::sets();
    }

    public function inject(array $collection)
    {
        $this->sets = array_merge($this->sets, $collection);
    }

    public function modify(string $string)
    {
        $string = str_replace(['Ş', 'ş', 'Ţ', 'ţ'], ['Ș', 'ș', 'Ț', 'ț'], $string);

        foreach ($this->sets as $reg => $newValue) {
            $string = preg_replace('/' . $reg . '/', $newValue, $string);
        }

        return $string;
    }
}