<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPseudoLocalization;

/**
 * http://qaz.wtf/u/convert.cgi?text=abcdefghijklmnopqrstuvwxy+%7C+0123456789+%7C+ABCDEFGHIKLMNOPQRSTVXYZ+%7C
 */
class AuroraPseudoLocalization
{
    protected array $groups
        = [
            'circled' => [
                'numbers' => '0①②③④⑤⑥⑦⑧⑨',
                'lower'   => 'ⓐⓑⓒⓓⓔⓕⓖⓗⓘⓙⓚⓛⓜⓝⓞⓟⓠⓡⓢⓣⓤⓥⓦⓧⓨ',
                'upper'   => 'ⒶⒷⒸⒹⒺⒻⒼⒽⒾⓀⓁⓂⓃⓄⓅⓆⓇⓈⓉⓋⓍⓎⓏ'
            ]
        ];
}
