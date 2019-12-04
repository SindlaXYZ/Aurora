<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Diacritics\Extension;

class Romanian
{
    public static function sets()
    {
        return [
            // A
            '(A|a)rbust(i|ilor)'                 => '$1rbușt$2',
            '(A|a)ctivitat(i|ile|ilor)'          => '$1ctivităț$2',
            '(A|a)genti(a|e|ile|lor|ilor)'       => '$1genți$2',
            '(A|a)manu(nt|ntul)'                 => '$1mănu$2',
            '(A|a)sigurar(i|ile|ilor)'           => '$1sigurăr$2',
            '(A|a)stazi'                         => '$1stăzi',

            // B
            '(B|b)atran(i|a)'                    => '$1ătrân$2',
            '(B|b)autur(a|ile|ilor)'             => '$1ăutur$2',
            '(B|b)alc(i|iuri|ului|iulilor)'      => '$1âlc$2',
            '(B|b)arc(i|ilor)'                   => '$1ărc$2',

            // C
            '(C|c)omunicati(i|e|ilor)'           => '$1omunicați$2',
            '(C|c)reati(i|e|ilor|ei)'            => '$1reați$2',
            '(C|c)rester(ea|ii)'                 => '$1reșter$2',
            '(C|c)arami(da|zilor)'               => '$1ărămi$2',
            '(C|c)hios(c|curi|curile)'           => '$1hioș$2',
            '(C|c)omert'                         => '$1omerț',
            '(C|c)alato(are|r|ri|rilor)'         => '$1ălăto$2',
            '(C|c)onstruct(ie|ia|ii|iile|iilor)' => '$1onstrucț$2',
            '(C|c)resti(n|ne|nism)'              => '$1rești$2',

            // D
            '(D|d)estinat(i|ie|ia|iile|iilor)'   => '$1estinaț$2',
            '(D|d)epozita(ri|rile)'              => '$1epozită$2',
            '(D|d)istract(ie|ii|iilor)'          => '$1istracț$2',
            '(D|d)esfasur(at|ate)'               => '$1esfășur$2',
            '(D|d)upa'                           => '$1upă',
            // E
            '(E|e)xtracti(e|a|i|ile|ilor)'       => '$1xtracți$2',
            '(E|e)xcepti(a|e)'                   => '$1xcepți$2',
            '(E|e)ntitat(i|ilor)'                => '$1ntităț$2',
            // F
            '(F|f)acilitat(i|ilor|ile)'          => '$1acilităț$2',
            '((\s|^)F|\sf)ara'                   => '$1ără', // fara != afara,
            '(F|f)orte(i|lor)'                   => '$1orțe$2',
            // G
            // H
            '(H|h)arti(a|ei|ilor)'               => '$1ârti$2',
            // I
            //'(I|i)nvataman(t|ul|ului)'           => '$1nvățămân$2',
            '(I|i)nvestigati(a|e|i|ilor)'        => '$1nvestigați$2',
            //'(I|i)nmultir(e|ea|ii|ilor)'         => '$1nmulțir$2',
            // J
            // K
            // L
            // M
            '(M|m)asi(na|nile|nii|nilor)'        => '$1ași$2',
            '(M|m)uniti(a|e|ei|ilor)'            => '$1uniți$2',
            // N
            '(N|n)avigat(ie|iei|iilor)'          => '$1avigaț$2',
            // O
            '(O|o)btin(ut|ute)'                  => '$1bțin$2',
            // P
            '(P|p)arint(i|ilor)'                 => '$1ărinț$2',
            '(P|p)iet(ei|elor)'                  => '$1ieț$2',
            '(P|p)rescolar'                      => '$1reșcolar',
            '(P|p)roducti(a|ei|ilor)'            => '$1roducți$2',
            '(P|p)ost(a|ale|ei|elor)'            => '$1oșt$2',
            // Q
            // R
            '(R|r)esapar(e|ea|ile)'              => '$1eșapar$2',
            // S
            '(S|s)uportilor'                     => '$1uporților',
            '(S|s)emint(e|elor)'                 => '$1eminț$2',
            '(S|s)crisa'                         => '$1crisă',
            '(S|s)culati'                        => '$1culați',
            //'(S|s)tiintif(ic|ica|ice)'           => '$1tiințif$2',
            // T
            '(T|t)aier(e|ea|ile)'                => '$1ăier$2',
            '(T|t)abaci(re|rea)'                 => '$1ăbăci$2',
            // U
            '(U|u)sil(e|or)'                     => '$1și$2',
            // V
            '(V|v)anza(re|rea)'                  => '$1ânza$2',
            // W
            // X
            // Y
            // Z
        ];
    }
}