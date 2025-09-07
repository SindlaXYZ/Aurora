<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Strink;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Strink
{
    protected string $string = '';

    public function string(string $string): self
    {
        $this->string = $string;
        return $this;
    }

    /**
     * Compress multiple spaces to a single one
     *
     * @in  The string  you want to   fix
     * @out The string you want to fix
     */
    public function compressSpaces(): self
    {
        $this->string = preg_replace('/\s\s+/', ' ', $this->string);
        return $this;
    }

    public function removeNewLines(string $replaceWith = ''): self
    {
        $this->string = str_replace(["\r", "\n"], $replaceWith, $this->string);
        return $this;
    }

    /**
     * Compress multiple slashes to a single one (from many ///// to one /)
     *
     * @in  this/is/a//very/bad/uri//
     * @out this/is/a/very/bad/uri/
     */
    public function compressSlashes(): self
    {
        $this->string = preg_replace('~(^|[^:])//+~', '\\1/', $this->string);
        return $this;
    }

    /**
     * Compress multiple double quotes to a single one
     *
     * @in  ""Pleașe țest thîs string""
     * @out "Pleașe țest thîs string"
     */
    public function compressDoubleQuotes(): self
    {
        $this->string = preg_replace('/"+/', '"', $this->string);
        return $this;
    }

    /**
     * Compress multiple simple quotes to a single one
     *
     * @in  '''Please 'test\" this string''
     * @out 'Please 'test\" this string'
     */
    public function compressSimpleQuotes(): self
    {
        $this->string = preg_replace("/'+/", "'", $this->string);
        return $this;
    }

    /**
     * Compress multiple simple and double quotes
     */
    public function compressQuotes(): self
    {
        $this->string = $this
            ->compressSimpleQuotes()
            ->compressDoubleQuotes()
            ->compressSimpleQuotes()
            ->compressDoubleQuotes();

        return $this;
    }

    /**
     * Generate a random string
     *
     * @param integer     $length
     * @param multi-array $keysToUse
     */
    public function randomString(int $length = 12, array $keysToUse = []): self
    {
        if (is_array($keysToUse) && count($keysToUse) == 0) {
            $keysToUse = [
                'abcdefghijklmnopqrstuwxyz',
                'ABCDEFGHIJKLMNOPQRSTUWXYZ',
                '0123456789',
                '!@#$%^&*+='
            ];
        }

        $password = '';
        $index    = 0;
        while (true) {
            if ($index > (count($keysToUse) - 1)) {
                $index = 0;
            }

            $password .= $keysToUse[$index][mt_rand(0, (strlen($keysToUse[$index]) - 1))];

            if (strlen($password) >= $length) {
                break;
            }

            ++$index;
        }

        $this->string = $password;
        return $this;
    }

    /**
     * Convert/obfuscate a string  with *
     *  eg: youremail@gmail.com > yo**************om
     *  eg: youremail@gmail.com > yo*****il@gmail.com
     */
    public function obfuscateString(mixed $string, int $margins = 2): string
    {
        return
            substr($string, 0, min($margins, max(0, strlen($string) - $margins)))
            . str_repeat('*', max(0, strlen($string) - ($margins * 2)))
            . substr($string, strlen($string) - $margins, $margins);
    }

    /**
     * Make a string shorter
     *
     * @TODO: rename to truncate
     */
    public function limitedString(int $limit = 10, string $postText = '...', string $cut = 'right'): self
    {
        if (strlen($this->string) > $limit) {

            $limit         = $limit + 1;
            $limitedString = $this->string;

            if ($cut == 'right') {
                $limitedString = mb_substr($this->string, 0, ($limit - strlen($postText)), 'utf-8') . $postText;

            } else if ($cut == 'middle' || $cut == 'center') {
                $left   = mb_substr($this->string, 0, (ceil($limit / 2) - strlen($postText)), 'utf-8');
                $center = $postText;
                $right  = mb_substr($this->string, (strlen($this->string) + 1) - (strlen($left) + strlen($center)) + $limit % 2, strlen($this->string), 'utf-8');

                $limitedString = $left . $center . $right;
            }

            $this->string = $limitedString;
        }

        return $this;
    }

    /**
     * Transform a snake_case string to camelCase or CamelCase
     * Translates a string with underscores into camel case (e.g. first_name -> firstName)
     */
    public function snakeCaseToCamelCase(bool $upperCaseFirstLetter = false): self
    {
        $this->string = str_replace('_', '', ucwords($this->string, '_'));
        $this->string = !$upperCaseFirstLetter ? lcfirst($this->string) : $this->string;

        return $this;
    }

    /**
     * Transform a snake_case string to "human case" or "Human case" or "Human Case".
     * Translates a string with underscores into human readable words (e.g. first_name -> first name).
     */
    public function snakeCaseToHumanCase(bool $upperCaseFirstLetter = false, bool $upperCaseAllLetters = false): self
    {
        $this->string = strtolower($this->string);

        $this->string = str_replace('_', ' ', $this->string);
        $this->string = ($upperCaseFirstLetter ? ucfirst($this->string) : $this->string);
        $this->string = ($upperCaseAllLetters ? ucwords($this->string) : $this->string);
        return $this;
    }

    /**
     * Transform a camelCase string to snake_case
     * Translates a camel case string into a string with underscores (e.g. firstName -> first_name)
     */
    public function camelCaseToSnakeCase(): self
    {
        $this->string = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->string));
        return $this;
    }

    public function lower(): self
    {
        $this->string = mb_strtolower($this->string, mb_detect_encoding($this->string));
        return $this;
    }

    public function upper(): self
    {
        $this->string = mb_strtoupper($this->string, mb_detect_encoding($this->string));
        return $this;
    }

    public function ucfirst(): self
    {
        $encoding = mb_detect_encoding($this->string);
        $this->string = mb_strtoupper(mb_substr($this->string, 0, 1, $encoding), $encoding)
            . mb_substr($this->string, 1, null, $encoding);
        return $this;
    }

    /**
     * Remove a list of words from sentence
     */
    public function removeWords(array $wordsList): self
    {
        foreach ($wordsList as $word) {
            $this->string = preg_replace("/\b{$word}\b/i", '', $this->string);
        }

        $this->compressSpaces();
        $this->string = trim($this->string);

        return $this;
    }

    public function replaceKeyValue(array $keyValueArray): self
    {
        foreach ($keyValueArray as $replaceThat => $withThis) {
            $this->string = str_replace($replaceThat, $withThis, $this->string);
        }

        return $this;
    }

    /**
     * Replace/transliterate accented characters with non accented
     * Remove diacritics from a string
     */
    public function transliterateUtf8String(): self
    {
        $sets = [
            'a' => ['á', 'à', 'â', 'ä', 'ã', 'å', 'ā', 'ă', 'ą', 'ǻ', 'ǎ'],
            'A' => ['Á', 'À', 'Â', 'Ä', 'Ã', 'Å', 'Ā', 'Ă', 'Ą', 'Ǻ', 'Ǎ'],

            'ae' => ['æ', 'ǽ'],
            'AE' => ['Æ', 'Ǽ'],

            'c' => ['ç', 'ć', 'ĉ', 'ċ', 'č'],
            'C' => ['Ç', 'Ć', 'Ĉ', 'Ċ', 'Č'],

            'd' => ['𝕕', '𝒹', 'đ', 'ď'],
            'D' => ['𝔻', '𝒟', 'Ð', 'Đ', 'Ď'],

            'e' => ['é', 'è', 'ê', 'ë', 'ē', 'ĕ', 'ė', 'ę', 'ě'],
            'E' => ['É', 'È', 'Ê', 'Ë', 'Ē', 'Ĕ', 'Ė', 'Ę', 'Ě'],

            'f' => ['ƒ'],
            'F' => ['ſ'],

            'g' => ['ĝ', 'ğ', 'ġ', 'ģ'],
            'G' => ['Ĝ', 'Ğ', 'Ġ', 'Ģ'],

            'h' => ['ĥ', 'ȟ', 'ḧ', 'ḣ', 'ḩ', 'ḥ', 'ḫ', 'ẖ', 'ħ', 'ⱨ', '𝒽', '𝕙'],
            'H' => ['Ĥ', 'Ȟ', 'Ḧ', 'Ḣ', 'Ḩ', 'Ḥ', 'Ḫ', 'H̱', 'Ħ', 'Ⱨ', 'ℋ', 'ℍ'],

            'i' => ['í', 'ì', 'ĭ', 'î', 'ǐ', 'ï', 'ḯ', 'ĩ', 'į', 'ī', 'ỉ', 'ȉ', 'ȋ', 'ị', 'ḭ', 'ɨ', 'ᵻ', 'ᶖ', 'ı'],
            'I' => ['Í', 'Ì', 'Ĭ', 'Î', 'Ǐ', 'Ï', 'Ḯ', 'Ĩ', 'Į', 'Ī', 'Ỉ', 'Ȉ', 'Ȋ', 'Ị', 'Ḭ', 'Ɨ', 'ꟾ', 'İ'],

            'j' => ['ĵ'],
            'J' => ['Ĵ'],

            'k' => ['ķ'],
            'K' => ['Ķ'],

            'l' => ['ĺ', 'ļ', 'ľ', 'ŀ', 'ł'],
            'L' => ['Ĺ', 'Ļ', 'Ľ', 'Ŀ', 'Ł'],

            'm' => ['𝔪', '𝕞', '𝓂'],
            'M' => ['𝔐', '𝕄', 'ℳ'],

            'n' => ['ń', 'ǹ', 'ň', 'ñ', 'ṅ', 'ņ', 'ṇ', 'ṋ', 'ṉ', 'n̈', 'ɲ', 'ƞ', 'ŋ', 'ꞑ', 'ᵰ', 'ᶇ', 'ɳ', 'ȵ', 'ŉ'],
            'N' => ['Ń', 'Ǹ', 'Ň', 'Ñ', 'Ṅ', 'Ņ', 'Ṇ', 'Ṋ', 'Ṉ', 'N̈', 'Ɲ', 'Ƞ', 'Ŋ', 'Ꞑ', '₦', 'ℕ'],

            'o' => ['ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ō', 'ŏ', 'ő', 'ǒ', 'ǿ', 'ơ'],
            'O' => ['Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ō', 'Ŏ', 'Ő', 'Ǒ', 'Ǿ', 'Ơ'],

            'r' => ['ŕ', 'ŗ', 'ř'],
            'R' => ['Ŕ', 'Ŗ', 'Ř'],

            's' => ['ś', 'ŝ', 'ş', 'š', 'ș', '𝔰'],
            'S' => ['Ś', 'Ŝ', 'Ş', 'Š', 'Ș'],

            't' => ['ţ', 'ť', 'ŧ', 'ț'],
            'T' => ['Ţ', 'Ť', 'Ŧ', 'Ț'],

            'u' => ['ù', 'ú', 'û', 'ü', 'ũ', 'ū', 'ŭ', 'ů', 'ű', 'ų', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'ư'],
            'U' => ['Ù', 'Ú', 'Û', 'Ü', 'Ũ', 'Ū', 'Ŭ', 'Ů', 'Ű', 'Ų', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ư'],

            'w' => ['ŵ'],
            'W' => ['Ŵ'],

            'y' => ['ŷ', 'ý', 'ÿ'],
            'Y' => ['Ŷ', 'Ý', 'Ÿ'],

            'z' => ['ź', 'ż', 'ž', '𝕫', '𝓏'],
            'Z' => ['Ź', 'Ż', 'Ž', 'ℤ', '𝒵'],

            '¿' => ['?'],

            "'" => ['´'],

            '-' => ['‑']
        ];

        //  'ß', ', , ' 'Ĳ',  'ĳ',   'Œ',  'œ',  'ų', ],

        foreach ($sets as $replacer => $accents) {
            foreach ($accents as $accent) {
                $this->string = str_replace($accent, $replacer, $this->string);
            }
        }

        return $this;
    }

    /**
     * Fix bad diacritics/accents
     */
    public function fixDiacritics(string $ISO6391 = 'ro'): self
    {
        $sets = [
            'ă' => ['ă', 'ã'],
            'Ă' => ['Ă'],
            'â' => ['â'],
            'Â' => ['Â'],
            'î' => ['î'],
            'Î' => ['Î'],
            'ș' => ['ş', 'º'],
            'Ș' => ['Ş', 'ª'],
            'ț' => ['ţ', 'þ'],
            'Ț' => ['Ţ', 'Þ']
        ];

        foreach ($sets as $expected => $accents) {
            foreach ($accents as $actual) {
                $this->string = str_replace($actual, $expected, $this->string);
            }
        }

        return $this;
    }

    public function pseudoTranslate(): self
    {
        /**
         * https://html.spec.whatwg.org/multipage/named-characters.html
         * https://www.charset.org/utf-8
         */
        $this->string = strtr($this->string, [
            '!'  => '¡',
            '"'  => '″',
            '#'  => '♯',
            '$'  => '€',
            '%'  => '‰',
            '&'  => '⅋',
            '\'' => '´',
            '('  => '{',
            ')'  => '}',
            '*'  => '⁎',
            '+'  => '⁺',
            ','  => '،',
            '-'  => '─', // ‐
            '.'  => '·',
            '/'  => '⁄',
            '0'  => '⓪',
            '1'  => '①', // ➊
            '2'  => '②', // ➋
            '3'  => '③', // ➌
            '4'  => '④', // ➍
            '5'  => '⑤', // ➎
            '6'  => '⑥', // ➏
            '7'  => '⑦', // ➐
            '8'  => '⑧', // ➑
            '9'  => '⑨', // ➒
            ':'  => '∶',
            ';'  => '⁏',
            '<'  => '≤',
            '='  => '≂',
            '>'  => '≥',
            '?'  => '¿',
            '@'  => '՞',
            'A'  => 'Å',
            'B'  => 'Ɓ',
            'C'  => 'Ç',
            'D'  => 'Ð',
            'E'  => 'É',
            'F'  => 'Ƒ',
            'G'  => 'Ĝ',
            'H'  => 'Ĥ',
            'I'  => 'Î',
            'J'  => 'Ĵ',
            'K'  => 'Ķ',
            'L'  => 'Ļ',
            'M'  => 'Ṁ',
            'N'  => 'Ñ',
            'O'  => 'Ö',
            'P'  => 'Ƥ',
            'Q'  => 'Ǫ',
            'R'  => 'Ŕ',
            'S'  => 'Š',
            'T'  => 'Ţ',
            'U'  => 'Û',
            'V'  => 'Ṽ',
            'W'  => 'Ŵ',
            'X'  => 'Ẋ',
            'Y'  => 'Ý',
            'Z'  => 'Ž',
            '['  => '⁅',
            '\\' => '∖',
            ']'  => '⁆',
            '^'  => '˄',
            '_'  => '‿',
            '`'  => '‵',
            'a'  => 'å',
            'b'  => 'ƀ',
            'c'  => 'ç',
            'd'  => 'ď',
            'e'  => 'é',
            'f'  => 'ƒ',
            'g'  => 'ĝ',
            'h'  => 'ĥ',
            'i'  => 'î',
            'j'  => 'ĵ',
            'k'  => 'ķ',
            'l'  => 'ļ',
            'm'  => 'ɱ',
            'n'  => 'ñ',
            'o'  => 'ö',
            'p'  => 'þ',
            'q'  => 'ų',
            'r'  => 'ŕ',
            's'  => 'š',
            't'  => 'ţ',
            'u'  => 'û',
            'v'  => 'ṽ',
            'w'  => 'ŵ',
            'x'  => 'ẋ',
            'y'  => 'ý',
            'z'  => 'ž',
            '{'  => '(',
            '|'  => '¦',
            '}'  => ')',
            '~'  => '˞',
        ]);

        return $this;
    }

    /**
     * Convert a string to-a-slug-one
     *
     * @docs    http://stackoverflow.com/questions/2955251/php-function-to-make-slug-url-string
     */
    public function slugify(bool $keepUTF8Chars = false): self
    {
        /* Not used yet:
        preg_match_all('/[A-Z]/', $this->string, $match);
        $caseUpper = count($match[0]);
        */

        preg_match_all('/[a-z]/', $this->string, $match);
        $caseLower = count($match[0]);

        if ($caseLower > 0) {
            $this->string = $this->camelCaseToSnakeCase($this->string);
        }

        // replace non letter or digits by -
        $this->string = preg_replace('/[^\pL\d]+/u', '-', $this->string);

        // transliterate
        if (!$keepUTF8Chars) {
            $this->string = $this->transliterateUtf8String($this->string);

            $this->string = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->string);

            // remove unwanted characters
            $this->string = preg_replace('/[^-\w]+/', '', $this->string);
        }

        // trim
        $this->string = trim($this->string, '-');

        // remove duplicate -
        $this->string = preg_replace('/-+/', '-', $this->string);

        // lowercase
        $this->string = mb_strtolower($this->string, mb_detect_encoding($this->string));

        return $this;
    }

    /**
     * Convert a multi lines string to array - convert every line intro a array element
     */
    public function linesToArray(): array
    {
        $linesArray = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->string) as $line) {
            $linesArray[] = $line;
        }

        return $linesArray;
    }

    public function classShortName(): self
    {
        $classParts   = explode('\\', $this->string);
        $this->string = end($classParts);

        return $this;
    }

    public function trim(?string $characters = null): self
    {
        $this->string = (null !== $characters ? trim($this->string, $characters) : trim($this->string));
        return $this;
    }

    public function leftTrim(?string $characters = null): self
    {
        $this->string = (null !== $characters ? ltrim($this->string, $characters) : ltrim($this->string));
        return $this;
    }

    public function rightTrim(?string $characters = null): self
    {
        $this->string = (null !== $characters ? rtrim($this->string, $characters) : rtrim($this->string));
        return $this;
    }

    public function countLowerCharacters(): int
    {
        preg_match_all('/\p{Ll}/u', $this->string, $matchesLower);
        return count($matchesLower[0]);
    }

    public function lowerCharactersPercentage(): float
    {
        $encoding = mb_detect_encoding($this->string);
        $total    = mb_strlen($this->string, $encoding);

        if (0 === $total) {
            return 0.0;
        }

        $lower = $this->countLowerCharacters();

        return BigDecimal::of($lower)
            ->dividedBy($total, 2, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toFloat();
    }

    public function countUpperCharacters(): int
    {
        preg_match_all('/\p{Lu}/u', $this->string, $matchesUpper);
        return count($matchesUpper[0]);
    }

    public function upperCharactersPercentage(): float
    {
        $encoding = mb_detect_encoding($this->string);
        $total    = mb_strlen($this->string, $encoding);

        if (0 === $total) {
            return 0.0;
        }

        $upper = $this->countUpperCharacters();

        return BigDecimal::of($upper)
            ->dividedBy($total, 2, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toFloat();
    }

    /**
     * https://qaz.wtf/u/convert.cgi?text=AaBbCcDdEe
     */
    public function obscure()
    {

    }

    public function strStartsWithAny(array $needles): bool
    {
        return array_any($needles, fn($needle, $_) => is_string($needle) && str_starts_with($this->string, $needle));
    }

    public function strEndsWithAny(array $needles): bool
    {
        return array_any($needles, fn($needle, $_) => is_string($needle) && str_ends_with($this->string, $needle));
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
