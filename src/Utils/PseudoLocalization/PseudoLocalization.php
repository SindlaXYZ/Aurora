<?php

namespace Sindla\Bundle\AuroraBundle\Utils\PseudoLocalization;

/**
 * http://qaz.wtf/u/convert.cgi?text=abcdefghijklmnopqrstuvwxy+%7C+0123456789+%7C+ABCDEFGHIKLMNOPQRSTVXYZ+%7C
 */
class PseudoLocalization
{
    protected $groups
        = [
            'circled' => [
                'numbers' => '0①②③④⑤⑥⑦⑧⑨',
                'lower'   => 'ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨ',
                'upper'   => 'ⒶⒷⒸⒹⒺⒻⒼⒽⒾⓀⓁⓂⓃⓄⓅⓆⓇⓈⓉⓋⓍⓎⓏ'
            ]
        ];
}
