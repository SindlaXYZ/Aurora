<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Strink;

class Strink
{
    protected $string;

    /**
     * @return Strink
     */
    public function string(string $string = null)
    {
        $this->string = $string;
        return $this;
    }

    /**
     * Compress multiple spaces to a single one
     *
     * @in  The string  you want to   fix
     * @out The string you want to fix
     *
     * @return  Strink
     */
    public function compressSpaces(): Strink
    {
        $this->string = preg_replace('/\s\s+/', ' ', $this->string);
        return $this;
    }

    /**
     * Compress multiple slashes to a single one (from many ///// to one /)
     *
     * @in  this/is/a//very/bad/uri//
     * @out this/is/a/very/bad/uri/
     *
     * @return Strink
     */
    public function compressSlashes(): Strink
    {
        $this->string = preg_replace('~(^|[^:])//+~', '\\1/', $this->string);
        return $this;
    }

    /**
     * Compress multiple double quotes to a single one
     *
     * @in  ""Pleașe țest thîs string""
     * @out "Pleașe țest thîs string"
     *
     * @return Strink
     */
    public function compressDoubleQuotes(): Strink
    {
        $this->string = preg_replace('/"+/', '"', $this->string);
        return $this;
    }

    /**
     * Compress multiple simple quotes to a single one
     *
     * @in  '''Please 'test\" this string''
     * @out 'Please 'test\" this string'
     *
     * @return Strink
     */
    public function compressSimpleQuotes(): Strink
    {
        $this->string = preg_replace("/'+/", "'", $this->string);
        return $this;
    }

    /**
     * Compress multiple simple and double quotes
     *
     * @return Strink
     */
    public function compressQuotes(): Strink
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
     *
     * @return Strink
     */
    public function randomString(int $length = 12, array $keysToUse = []): Strink
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
     *
     * @param     $string
     * @param int $margins
     *
     * @return string
     */
    public function obfuscateString($string, $margins = 2)
    {
        return
            substr($string, 0, min(2, strlen($string) - $margins))
            . str_repeat('*', max(0, strlen($string) - ($margins * 2)))
            . substr($string, strlen($string) - $margins, $margins);
    }

    /**
     * Make a string shorter
     *
     * @param integer $limit
     * @param string  $postText
     *
     * @return Strink
     */
    public function limitedString(int $limit = 10, string $postText = '...', string $cut = 'right'): Strink
    {
        if (strlen($this->string) > $limit) {

            $limit = $limit + 1;

            if ($cut == 'right') {
                $this->string = mb_substr($this->string, 0, ($limit - strlen($postText)), 'utf-8') . $postText;

            } else if ($cut == 'middle' || $cut == 'center') {
                $this->string = mb_substr($this->string, 0, (round($limit / 2) - strlen($postText)), 'utf-8');
                $this->string .= $postText;
                $this->string .= mb_substr($this->string, strlen($this->string) - round($limit / 2), strlen($this->string), 'utf-8');
            }
        }

        return $this;
    }

    /**
     * Transform a snake_case string to camelCase or CamelCase
     * Translates a string with underscores into camel case (e.g. first_name -> firstName)
     *
     * @param boolean $upperCaseFirsLetter
     *
     * @return Strink
     */
    public function snakeCaseToCamelCase(bool $upperCaseFirsLetter = false): Strink
    {
        $this->string = strtolower($this->string);

        if (strpos($this->string, '_')) {
            $function     = create_function('$c', 'return strtoupper($c[1]);');
            $this->string = preg_replace_callback('/_([a-z])/', $function, $this->string);
        }

        $this->string = ($upperCaseFirsLetter ? ucfirst($this->string) : $this->string);
        return $this;
    }

    /**
     * Transform a snake_case string to "huma case" or "Human case" or "Human Case"
     * Translates a string with underscores into camel case (e.g. first_name -> first name)
     *
     * @param bool $upperCaseFirsLetter
     * @param bool $upperCaseAllLetter
     *
     * @return Strink
     */
    public function snakeCaseToHumanCase(bool $upperCaseFirsLetter = false, bool $upperCaseAllLetter = false): Strink
    {
        $this->string = strtolower($this->string);

        $this->string = str_replace('_', ' ', $this->string);
        $this->string = ($upperCaseFirsLetter ? ucfirst($this->string) : $this->string);
        $this->string = ($upperCaseAllLetter ? ucwords($this->string) : $this->string);
        return $this;
    }

    /**
     * Transform a camelCase string to snake_case
     * Translates a camel case string into a string with underscores (e.g. firstName -> first_name)
     *
     * @return Strink
     */
    public function camelCaseToSnakeCase(): Strink
    {
        $this->string[0] = strtolower($this->string[0]);
        $function        = create_function('$c', 'return "_" . strtolower($c[1]);');
        $this->string    = preg_replace_callback('/([A-Z])/', $function, $this->string);
        return $this;
    }

    /**
     * Remove a list of words from sentence
     *
     * @param array $wordsList
     * @return  Strink
     */
    public function removeWords(array $wordsList): Strink
    {
        foreach ($wordsList as $word) {
            $this->string = preg_replace("/\b{$word}\b/i", '', $this->string);
        }

        $this->string = trim($this->compressSpaces($this->string));
        return $this;
    }

    /**
     * @param array $keyValueArray
     * @return  Strink
     */
    public function replaceKeyValue(array $keyValueArray): Strink
    {
        foreach ($keyValueArray as $replaceThat => $withThis) {
            $this->string = str_replace($replaceThat, $withThis, $this->string);
        }

        return $this;
    }

    /**
     * Replace/transliterate accented characters with non accented
     * Remove diacritics from a string
     *
     * @return Strink
     */
    public function transliterateUtf8String(): Strink
    {
        $sets = [
            'a' => ['á', 'à', 'â', 'ä', 'ã', 'å', 'ā', 'ă', 'ą', 'ǻ', 'ǎ'],
            'A' => ['Á', 'À', 'Â', 'Ä', 'Ã', 'Å', 'Ā', 'Ă', 'Ą', 'Ǻ', 'Ǎ'],

            'ae' => ['æ', 'ǽ'],
            'AE' => ['Æ', 'Ǽ'],

            'c' => ['ç', 'ć', 'ĉ', 'ċ', 'č'],
            'C' => ['Ç', 'Ć', 'Ĉ', 'Ċ', 'Č'],

            'd' => ['đ', 'ď'],
            'D' => ['Ð', 'Đ', 'Ď'],

            'e' => ['é', 'è', 'ê', 'ë', 'ē', 'ĕ', 'ė', 'ę', 'ě'],
            'E' => ['É', 'È', 'Ê', 'Ë', 'Ē', 'Ĕ', 'Ė', 'Ę', 'Ě'],

            'f' => ['ƒ'],
            'F' => ['ſ'],

            'g' => ['ĝ', 'ğ', 'ġ', 'ģ'],
            'G' => ['Ĝ', 'Ğ', 'Ġ', 'Ģ'],

            'h' => ['ĥ', 'ȟ', 'ḧ', 'ḣ', 'ḩ', 'ḥ', 'ḫ', 'ẖ', 'ħ', 'ⱨ'],
            'H' => ['Ĥ', 'Ȟ', 'Ḧ', 'Ḣ', 'Ḩ', 'Ḥ', 'Ḫ', 'H̱', 'Ħ', 'Ⱨ'],

            'i' => ['í', 'ì', 'ĭ', 'î', 'ǐ', 'ï', 'ḯ', 'ĩ', 'į', 'ī', 'ỉ', 'ȉ', 'ȋ', 'ị', 'ḭ', 'ɨ', 'ᵻ', 'ᶖ', 'ı'],
            'I' => ['Í', 'Ì', 'Ĭ', 'Î', 'Ǐ', 'Ï', 'Ḯ', 'Ĩ', 'Į', 'Ī', 'Ỉ', 'Ȉ', 'Ȋ', 'Ị', 'Ḭ', 'Ɨ', 'ꟾ', 'İ'],

            'j' => ['ĵ'],
            'J' => ['Ĵ'],

            'k' => ['ķ'],
            'K' => ['Ķ'],

            'l' => ['ĺ', 'ļ', 'ľ', 'ŀ', 'ł'],
            'L' => ['Ĺ', 'Ļ', 'Ľ', 'Ŀ', 'Ł'],

            'n' => ['ń', 'ǹ', 'ň', 'ñ', 'ṅ', 'ņ', 'ṇ', 'ṋ', 'ṉ', 'n̈', 'ɲ', 'ƞ', 'ŋ', 'ꞑ', 'ᵰ', 'ᶇ', 'ɳ', 'ȵ', 'ŉ'],
            'N' => ['Ń', 'Ǹ', 'Ň', 'Ñ', 'Ṅ', 'Ņ', 'Ṇ', 'Ṋ', 'Ṉ', 'N̈', 'Ɲ', 'Ƞ', 'Ŋ', 'Ꞑ', '₦'],

            'o' => ['ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ō', 'ŏ', 'ő', 'ǒ', 'ǿ', 'ơ'],
            'O' => ['Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ō', 'Ŏ', 'Ő', 'Ǒ', 'Ǿ', 'Ơ'],

            'r' => ['ŕ', 'ŗ', 'ř'],
            'R' => ['Ŕ', 'Ŗ', 'Ř'],

            's' => ['ś', 'ŝ', 'ş', 'š', 'ș'],
            'S' => ['Ś', 'Ŝ', 'Ş', 'Š', 'Ș'],

            't' => ['ţ', 'ť', 'ŧ', 'ț'],
            'T' => ['Ţ', 'Ť', 'Ŧ', 'Ț'],

            'u' => ['ù', 'ú', 'û', 'ü', 'ũ', 'ū', 'ŭ', 'ů', 'ű', 'ų', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'ư'],
            'U' => ['Ù', 'Ú', 'Û', 'Ü', 'Ũ', 'Ū', 'Ŭ', 'Ů', 'Ű', 'Ų', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ư'],

            'w' => ['ŵ'],
            'W' => ['Ŵ'],

            'y' => ['ŷ', 'ý', 'ÿ'],
            'Y' => ['Ŷ', 'Ý', 'Ÿ'],

            'z' => ['ź', 'ż', 'ž',],
            'Z' => ['Ź', 'Ż', 'Ž'],

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
     *
     * @return Strink
     */
    public function fixDiacritics(string $ISO6391 = 'ro')
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

    /**
     * Convert a string to-a-slug-one
     *
     * @return Strink
     * @docs    http://stackoverflow.com/questions/2955251/php-function-to-make-slug-url-string
     */
    public function slugify(bool $keepUTF8Chars = false): Strink
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
     *
     * @return array
     */
    public function linesToArray(): array
    {
        $linesArray = [];
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $this->string) as $line) {
            $linesArray[] = $line;
        }

        return $linesArray;
    }

    /**
     * https://qaz.wtf/u/convert.cgi?text=AaBbCcDdEe
     */
    public function obscure()
    {

    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->string;
    }
}