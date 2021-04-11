<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Sanitizer;

// Symfony
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

// Vendor
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;
use MatthiasMullie\Minify;

/**
 * EXPERIMENTAL
 *
 * Debug: php bin/console debug:container aurora.sanitizer
 *
 * https://gist.github.com/tovic/d7b310dea3b33e4732c0
 *
 * @package AuroraBundle\Utils
 */
class Sanitizer
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Remove CSS comments
     *
     * @param string $css
     * @return string
     */
    public function cssClearComments(string $css): string
    {
        /**
         * Usage:
         *  $css = preg_replace(array_keys($regexRemoveCSSComments), $regexRemoveCSSComments, $css);
         */
        $regexRemoveCSSComments = [
            "`^([\t\s]+)`ism"                       => '',
            "`^\/\*(.+?)\*\/`ism"                   => "",
            "`(\A|[\n;]+)/\*.+?\*/`s"               => "$1",
            "`(\A|[;\s]+)//.+\R`"                   => "$1\n",
            "`(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+`ism" => "\n"
        ];

        return preg_replace(array_keys($regexRemoveCSSComments), $regexRemoveCSSComments, $css);
    }

    /**
     * Minify a CSS content, and change url(path) relative to css file
     *
     * @param string      $css
     * @param string|null $asset
     * @return mixed
     */
    public function cssMinify(string $css, ?string $asset = null)
    {
        $assetBasename = $asset ? basename($asset) : null;
        $assetBaseDir  = $asset ? str_ireplace($assetBasename, '', $asset) : null;

        $css      = $this->cssClearComments($css);
        $minifier = new Minify\CSS();
        $AuroraMatch    = new AuroraMatch();

        // TODO: parse line by line

        if (false) {
            /* Version 1 (Buggy) */
            preg_match_all("/url\((?!['\"]?(?:data|https|http):)['\"]?([^'\"\)]*)['\"]?\)/", $css, $matches);
            foreach ($matches[0] as $urlToImport) {
                $quote = '';
                if (0 === strpos($urlToImport, "url('")) {
                    $quote = "'";
                } else if (0 === strpos($urlToImport, 'url("')) {
                    $quote = '"';
                }
                $urlToImport2 = preg_replace("/url\('?\"?/i", "url({$quote}{$assetBaseDir}", $urlToImport);
                $css          = str_replace($urlToImport, $urlToImport2, $css);
            }
        } else {
            /* Version 2 */
            $cssLineByLine = '';
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $css) as $line) {
                $matches = $AuroraMatch->matchCssUrls($line);

                if (isset($matches[0]) & !empty($matches[0])) {
                    foreach ($matches[0] as $urlToImport) {
                        $quote = '';
                        if (0 === strpos($urlToImport, "url('")) {
                            $quote = "'";
                        } else if (0 === strpos($urlToImport, 'url("')) {
                            $quote = '"';
                        }
                        $urlToImport2 = preg_replace("/url\('?\"?/i", "url({$quote}{$assetBaseDir}", $urlToImport);
                        $line         = str_replace($urlToImport, $urlToImport2, $line);
                    }
                }

                $cssLineByLine .= $line . "\n";
            }

            $css = $cssLineByLine;
        }

        $minifier->add($css);
        return $minifier->minify();
    }


    public function minifyHTML($Html)
    {
        $Search = [
            '/(\n|^)(\x20+|\t)/',
            '/(\n|^)\/\/(.*?)(\n|$)/',
            '/\n/',
            '/\<\!--.*?-->/',
            '/(\x20+|\t)/', # Delete multispace (Without \n)
            '/\>\s+\</', # strip whitespaces between tags
            '/(\"|\')\s+\>/', # strip whitespaces between quotation ("') and end tags
            '/=\s+(\"|\')/']; # strip whitespaces between = "'

        $Replace = [
            "\n",
            "\n",
            " ",
            "",
            " ",
            "><",
            "$1>",
            "=$1"];

        $Html = preg_replace($Search, $Replace, $Html);
        return $Html;
    }

    // HTML Minifier
    public function htmlMinify($input)
    {
        if (trim($input) === "") return $input;

        // Remove extra white-space(s) between HTML attribute(s)
        $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function ($matches) {
            return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
        }, str_replace("\r", "", $input));

        // Minify inline CSS declaration(s)
        if (strpos($input, ' style=') !== false) {
            $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function ($matches) {
                return '<' . $matches[1] . ' style=' . $matches[2] . $this->minifyCSS($matches[3]) . $matches[2];
            }, $input);
        }

        if (strpos($input, '</style>') !== false) {
            $input = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', function ($matches) {
                return '<style' . $matches[1] . '>' . $this->minifyCSS($matches[2]) . '</style>';
            }, $input);
        }

        if (strpos($input, '</script>') !== false) {
            $input = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function ($matches) {
                return '<script' . $matches[1] . '>' . $this->minifyCSS($matches[2]) . '</script>';
            }, $input);
        }

        return preg_replace(
            [
                // t = text
                // o = tag open
                // c = tag close
                // Keep important white-space(s) after self-closing HTML tag(s)
                '#<(img|input)(>| .*?>)#s',
                // Remove a line break and two or more white-space(s) between tag(s)
                '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
                '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
                '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
                '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
                '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
                '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
                '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
                // Remove HTML comment(s) except IE comment(s)
                '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
            ],
            [
                '<$1$2</$1>',
                '$1$2$3',
                '$1$2$3',
                '$1$2$3$4$5',
                '$1$2$3$4$5$6$7',
                '$1$2$3',
                '<$1$2',
                '$1 ',
                '$1',
                ""
            ],
            $input);
    }

    private function minifyHTMLV1($buffer)
    {
        $search = [
            '/\>[^\S ]+/s', // strip whitespaces after tags, except space
            '/[^\S ]+\</s', // strip whitespaces before tags, except space
            '/\t/',
            '/(\s)+/s',       // shorten multiple whitespace sequences
            '/<!--(.*?)-->/'
        ];

        $replace = [
            '>',
            '<',
            '\\1',
            ' ',
            ''
        ];

        $buffer = preg_replace('/\s+/', ' ', preg_replace($search, $replace, $buffer));

        return $buffer;
    }

    // CSS Minifier => http://ideone.com/Q5USEF + improvement(s)
    public function minifyCSS($input)
    {
        if (trim($input) === "") return $input;
        return preg_replace(
            [
                // Remove comment(s)
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
                // Remove unused white-space(s)
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~+]|\s*+-(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
                // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
                '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
                // Replace `:0 0 0 0` with `:0`
                '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
                // Replace `background-position:0` with `background-position:0 0`
                '#(background-position):0(?=[;\}])#si',
                // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
                '#(?<=[\s:,\-])0+\.(\d+)#s',
                // Minify string value
                '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
                '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
                // Minify HEX color code
                '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
                // Replace `(border|outline):none` with `(border|outline):0`
                '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
                // Remove empty selector(s)
                '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
            ],
            [
                '$1',
                '$1$2$3$4$5$6$7',
                '$1',
                ':0',
                '$1:0 0',
                '.$1',
                '$1$3',
                '$1$2$4$5',
                '$1$2$3',
                '$1:0',
                '$1$2'
            ],
            $input);
    }

    public function minifyJS($input, $removeConsoleOutputs = false)
    {
        $minifiedJS = $this->minifyJSV2($input);

        if ($removeConsoleOutputs) {
            $minifiedJS = preg_replace('/(?<console>(?:\/\/)?\s*console\.[^;]+;)/', '', $minifiedJS);
        }

        return $minifiedJS;
    }

    // JavaScript Minifier
    private function minifyJSV1($input)
    {
        if (trim($input) === "") return $input;
        return preg_replace(
            [
                // Remove comment(s)
                '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
                // Remove white-space(s) outside the string and regex
                '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
                // Remove the last semicolon
                '#;+\}#',
                // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
                '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
                // --ibid. From `foo['bar']` to `foo.bar`
                '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
            ],
            [
                '$1',
                '$1$2',
                '}',
                '$1$3',
                '$1.$3'
            ],
            $input);
    }

    private function minifyJSV2($string)
    {
        if (trim($string) === "") return $string;

        $string = preg_replace("/(\*\/\s*)\/\/(?!(\*\/|[^\r\n]*?[\\\\n\\\\r]+\s*\"\s*\+|[^\r\n]*?[\\\\n\\\\r]+\s*\'\s*\+))[^\n\r\;]*?[^\;]\s*[\n\r]/", "$1\n", $string);
        do {
            $string = preg_replace("/(http(s)?\:)([^\r\n]*?)(\/\/)/", "$1$3qDdXX", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(^^\s*\/)(\/).*/", "\n", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([\r\n]+?\s*|\,\s*|\;\s*|\|\s*|\)\s*|\+\s*|\&\s*|\{\s*|\}\s*|\]\s*|\[\s*|\+\s*|\'\s*|\"\s*|\:\s*|-\s*)((\/)(\/)+)([^\r\n\'\"]*?[nte]'[a-z])*?(?!([^\r\n]*?)([\'\"]|[\\\\]|\*\/|[=]+\s*\";|[=]+\s*\';)).*/", "$1\n", $string);
        $string = preg_replace("/(^^\s*\/\*)(?!([\'\"]))[\s\S]*?(\*\/)/", "\n \n", $string);
        $string = preg_replace("/(\|\|\s*|=\s*|[\n\r]|\;\s*|\,\s*|\{\s*|\}\s*|\+\s*|\?\s*)((?!([\'\"]))\/\*)(?!(\*\/))[\s\S]*?(\*\/)/", "$1\n", $string);
        $string = preg_replace("/(\;\s*|\,\s*|\{\s*|\}\s*|\+\s*|\?\s*|[\n\r]\s*)((?!([\'\"]))\/\*)(?!(\*\/))[\s\S]*?(\*\/)/", "$1\n", $string);
        do {
            $string = preg_replace('/([^\/\"\'\*a-zA-Z0-9\>])\/\*(?!(\*\/))[^\n\r@]*?\*\/(?=([\/\"\'\\\\\*a-zA-Z0-9\>\s=\)\(\,:;\.\}\{\|\]\[]))/', "$1", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([\;\n\r]\s*)\/\/.*/", "$1\n", $string);
        $string = preg_replace("/(\/\s\/)([g][\W])/", "ZUQQ$2", $string);
        $string = preg_replace("/\\\\n/", "AQerT", $string);
        $string = preg_replace("/\\\\r/", "BQerT", $string);
        $string = preg_replace("/([^\*])(\*|[\r\n]|\'|\"|\,|\+|\{|;|\(|\)|\[|\]|\{|\}|\?|[^p|s]:|\&|\%|[^\\\\][a-m-o-u-s-zA-Z]|\||-|=|[0-9])(\s*)(?!([^=\\\\\&\/\"\'\^\*:]))(\/)(\/)+(?!([\r\n\*\+\"]*?([^\r\n]*?\*\/|[^\r\n]*?\"\s*\+|([^\r\n]*?=\";))))([^\n\r]*)([^;\"\'\{\(\}\,]\s*[\\\\\[])(?=([\r\n]+))/", "$1$2$3", $string);
        $string = preg_replace("/((([\r\n]\s*)(\/\*[^\r\n]*?\*\/(?!([^\n\r]*?\"\s*\+)))([^\n\r]*?\/\*[^\n\r]*?\*\/(?!([^\n\r]*?\"\s*\+))[^\n\r]*?\/\*[^\n\r]*?\*\/(?!([^\n\r]*?\"\s*\+)))+)+(?!([\*]))(?=([^\n\r\/]*?\/\/\/)))/", "$3", $string);
        $string = preg_replace("/([\r\n]+?\s*)((\/)(\/)+)(?!([^\r\n]*?)([\\\\]|\*\/|[=]+\s*\";|[=]+\s*\';)).*/", "$1\n", $string);
        $string = preg_replace("/(\'\s*)(\/\/\*)([^\r\n\*]*?(?!(\*\/))(\'))/", "$1TDdXX$3", $string);
        $string = preg_replace("/(\"\s*)(\/\/\*)([^\r\n\*]*?(?!(\*\/))(\"))/", "$1TDdXX$3", $string);
        $string = preg_replace("/(\'\s*)(\/\*)([^\r\n\*]*?(?!(\*\/))(\'))/", "$1pDdYX$3", $string);
        $string = preg_replace("/(\"\s*)(\/\*)([^\r\n\*]*?(?!(\*\/))(\"))/", "$1pDdYX$3", $string);
        $string = preg_replace("/(\,\s*)(\*\/\*)(\s*[\}\"\'\;\)])/", "$1RDdPK$3", $string); // , */* '
        $string = preg_replace('/(\n|\r|\+|\&|\=|\|\||\(|[^\)]\:[^\=\,\/\$\\\\\<]|\(|return(?!(\/[a-zA-Z]+))|\!|\,)(?!(\s*\/\/|\n))(\s*\/)([^\]\)\}\*\;\,gi\.]\s*)([^\/\n]*?)(\*\/)/', '$1$4$5$6ODdPK', $string);
        $string = preg_replace("/[\/][\/]+(AQerTBQerT)(\s*[\"]\s*[\+])/", "WQerT", $string);
        $string = preg_replace("/[\/][\/]+(\*\/AQerTBQerT)(\s*[\"]\s*[\+])/", "YQerT", $string);
        $string = preg_replace("/([\r\n]\s*\/\/)[^\r\n]*?\/\*(?=(\/))[^\r\n]*?([\r\n])/", "$1 */$3", $string);
        $string = preg_replace("/([\)]|[^\/|\\\\|\"])(\/\*)(?=([^\r\n]*?[\\\\][rn]([\\\\][nr])?\s*\"\s*\+\s*(\n|\r)?\s*\"))/", "$1pDdYX", $string);
        $string = preg_replace('/([\"]\s*[\,\+][\r\n]\s*[\"])(\s*\/\/)((\/\/)|(\/))*/', '$1qDdXX', $string);
        $string = preg_replace('/([\"]\s*[\,\+][\r\n]\s*[\"](qDdXX))[\\\\]*(\s*\/\/)*((\/\/)|(\/))*/', '$1', $string);
        $string = preg_replace("/([\r\n]\s*)(?=([^\r\n\*\,\:\;a-zA-Z\"]*?))(\/)+(\/)[^\r\n\/][^\r\n\*\,]*?[\*]+(?!([^\r\n]*?(([^\r\n]*?\/|\"\s*\)\s*\;|\"\s*\;|\"\s*\,|\'\s*\)\s*\;|\'\s*\;|\'\s*\,))))[^\r\n]*(?!([\/\r\n]))[^\r\n]*/", "$1", $string);
        $string = preg_replace("/([\r\n](\/)*[^:\;\,\.\+])(\/\/[^\r\n]*?)(\*)?([^\r\n]+?)(\*)+([^\r\n\*\/])+?(\/[^\*])(?!([^\r\n]*?((\"\s*\)\s*\;|\"\s*\;|\"\s*\,|\'\s*\)\s*\;|\'\s*\;|\'\s*\,))))/", "$1$3$7$8", $string);
        do {
            $string = preg_replace("/([\r\n])((\/)*[^:\;\,\.\+])(\/\/[^\r\n]*?)(\*)?([^\r\n]+?)(\/|\*)([^\r\n]*?)(\*)[\r\n]/", "$1", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/(((([\r\n](?=([^:;,\.\+])))(\/)+(\/))(\*))([^\r\n]*?)(\/\*)*([^\r\n])*?(\*\/)(?!([^\r\n]*?((\"\s*\)\s*\;|\"\s*\;|\"\s*\,|\'\s*\)\s*\;|\'\s*\;|\'\s*\,))))(((?=([^:\;\,\.\+])))(\/)*([^\r\n]*?)(\*|\/)?([^\r\n]*?)(\/\*)([^\r\n])*?(\*\/)(?!([^\r\n]*?((\"\s*\)\s*\;|\"\s*\;|\"\s*\,|\'\s*\)\s*\;|\'\s*\;|\'\s*\,)))))*)+[^\r\n]*/", "$2$7$9$10$11$12", $string);
        $string = preg_replace("/(\/\*[\r\n]\s*)(?!([^\/;:%~`#@&-_=,\.\$\^\{\[\(\|\)\*\+\?\'\"\a-zA-Z0-9]))(((\/\*)[^\r\n]*?(\*\/)?[^\r\n]*?(\/\*)[^\r\n]*?(\*\/))*((\/\*)[^\r\n]*?(\*\/)))+(?!([^\r\n]*?(\*\/|\/\*)))[^\r\n]*?[\r\n]/", "\n", $string);
        $string = preg_replace("/(?!([\r\n]))([^a-zA-Z0-9]\+|\?|&|\=|\|\||\!|\(|,|return(?!(\/[a-zA-Z]+))|[^\)]\:)(?!(\s*\/\/|\n|\/\*[^\r\n\*]*?\*\/))(\s*\/([\*\^]?))(?!([\r\n\*\/]|[\*]))(?!(\<\!\-\-))(([^\^\]\)\}\*;,g&\.\"\']?\s*)(?=([\]\)\}\*;,g&\.\/\"\']))?)(([^\r\n]*?)(([\w\W])([\*]?\/\s*)(\})|([^\\\\])([\*]?\/\s*)(\))|([\w\W])([\*]?\/\s*)([i][g]?[\W])|([\w\W])([\*]?\/\s*)([g][i]?[\W])|([\w\W])([\*]?\/\s*)(?=(\,))|([^\\\\]|[\/])([\*]?\/\s*)(;)|([\w\W])([\*]?\/\:\s)(?!([@\]\[\)\(\}\{\.,#%\+-\=`~\*&\^;\:\'\"]))|([^\\\\])([\*]?\/\s*)(\.[^\/])|([^\\\\])([\*]?\/\s*)([\r\n]\s*[;\.,\)\}\]]\s*[^\/]|[\r\n]\s*([i][g]?[\W])|[\r\n]\s*([g][i]?[\W])))|([^\\\\])([\*]?\/\s*)([;\.,\)\}\]]\s*[^\/]|([i][g]?[\W])|([g][i]?[\W])))/", "$2$3$5AwTc$7$8$10$13$15$18$21$24$27$30$33$36$39$44CwRc$16$17$19$20$22$23$25$26$28$31$32$34$35$37$38$40$41$45$46", $string);
        $string = preg_replace("/([^;\"\'\{\(\}\,\/]\s*[^\/][\\\\\[]\s?)\s*([\r\n]+)/", "$1", $string);
        $string = preg_replace("/([\|\[])\s*([\|\]])/", "$1$2", $string);
        do {
            $string = preg_replace('/(AwTc)([^\r\nC]*?)(\/\*)(?=([^\r\n]*?CwRc))/', '$1$2pDdYX', $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace('/(AwTc)([^\r\nC]*?)(\*\/)(?=([^\r\n]*?CwRc))/', '$1$2ODdPK', $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace('/(AwTc)([^\r\nC]*?)(\/\/)(?=([^\r\n]*?CwRc))/', '$1$2qDdXX', $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([^\(\/\"\']\s*)(?!\(\s*function)((\()(?=([^\n\r\)]*?[\'\"]))(?!([^\r\n]*?\"\s*\\s*\"|[^\r\n]*?\"\s*\\\\\s*\"|[^\r\n]*?\"\s*\[[^\r\n]*?\]\s*\"))((?>[^()]+)|(?2))*?\))(?!(\s*\"\s*\;|\s*\'\s*\;|\s*\/|\s*\)|\s*\"|[^\n\r]*?\"\s*\+\s*(\n|\r)?\s*\"))/", "$1 /*Yu*/ $2 /*Zu*/ ", $string);
        do {
            $string = preg_replace('/(\/\*Yu\*\/)([^\r\n]*?)(\/)(\/)(?=([^\r\n]*?\/\*Zu\*\/))/', '$1$2qDdXX', $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\/\*Yu\*\/)([^\n\r\'\"]*?[\"\'])([^\n\r\)]*?)(\/\*)([^\n\r\'\"\)]*?[\"\'])([^\n\r]*?\/\*Zu\*\/)/", "$1$2$3pDdYX$5$6", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\/\*Yu\*\/)([^\n\r\'\"]*?[\"\'])([^\n\r\)]*?)(\*\/)([^\n\r\'\"\)]*?[\"\'])([^\n\r]*?\/\*Zu\*\/)/", "$1$2$3ODdPK$5$6", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=|\+|\(|[a-z]|\,)(\s*)(\")([^\r\n\;\/\'\)\,\]\}\*]*?)(\/)(\/)([^\r\n\;\"\*]*?)(\")/", "$1$2$3$4qDdXX$7$8", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=|\+|\(|[a-z]|\,)(\s*)(\')([^\r\n\;\/\'\)\,\]\}\*]*?)(\/)(\/)([^\r\n\*\;\']*?)(\')/", "$1$2$3$4qDdXX$7$8", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\"[^\r\n\;]*?)(\/)(\/)([^\r\n\"\;]*?([\"]\s*(\;|\)|\,)))/", "$1qDdXX$4", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\'[^\r\n\;]*?)(\/)(\/)([^\r\n\'\;]*?([\']\s*(\;|\)|\,)))/", "$1qDdXX$4", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([\n\r])([^\n\r\*\,\"\']*?)(?=([^\*\,\:\;a-zA-Z\"]*?))(\/)(\/)+(?=([^\n\r]*?\*\/))([^\n\r]*?(\*\/)).*/", "$1$4$5 $8", $string);
        do {
            $string = preg_replace("/([\r\n]\s*)((\/\*(?!(\*\/)))([^\r\n]+?)(\*\/))(?!([^\n\r\/]*?(\/)(\/)+\*))/", "$1$3$6", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([\n\r]\/)(\/)+([^\n\r]*?)(\*\/)([^\n\r]*?(\*\/))(?!([^\n\r]*?(\*\/)|[^\n\r]*?(\/\*))).*/", "$1/ $4", $string);
        do {
            $string = preg_replace("/([\n\r]\s*\/\*\*\/)([^\n\r=]*?\/\*[^\n\r]*?\*\/)(?=([\n\r]|\/\/))/", "$1", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([\n\r]\s*\/\*\*\/)([^\n\r=]*?)(\/\/.*)/", "$1$2", $string);
        do {
            $string = preg_replace("/(\=\s*)(?=([^\r\n\'\"]*?\'[^\n\r\']*?\'))([^\n\r;]*?[;]\s*)(\/\/[^\r\n][^\r\n]*)[\n\r]/", "$1$3", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\=)(\s*\')([^\r\n\'\"]*?)(\/)(\/)([^\r\n]*?[\'])/", "$1$2$3qDdXX$6", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\"[^\r\n\;\,\"]*?)(\/)(\*)(?!([YZ]u\*\/))([^\r\n;\,\"]*?)(\")/", "$1pDdYX$5$6", $string, 1, $count);
        } while ($count);   // open
        do {
            $string = preg_replace("/([^\"]\"[^\r\n\;\/\,\"]*?)(\s*)(\*)(\/)([^\r\n;\,\"=]*?)(\")/", "$1$2ODdPK$5$6", $string, 1, $count);
        } while ($count);   // close
        do {
            $string = preg_replace("/(\'[^\r\n\;\,\']*?)(\/)(\*)(?!([YZ]u\*\/))([^\r\n;\,\']*?)(\')/", "$1pDdYX$5$6", $string, 1, $count);
        } while ($count);   // open
        do {
            $string = preg_replace("/(\'[^\r\n\;\/\,\']*?)(\s*)(\*)(\/)([^\r\n;\,\']*?)(\')/", "$1$2ODdPK$5$6", $string, 1, $count);
        } while ($count);   // close
        do {
            $string = preg_replace("/(\'[^\r\n\;\,\']*?)(\*)(\/)([^\r\n;\,\']*?)(\')(?!([^\n\r\+]*?[\']))/", "$1ODdPK$4$5", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\"[^\r\n\;\,\"]*?)(\*)(\/)([^\r\n;\,\"]*?)(\")(?!([^\n\r\+]*?[\"]))/", "$1ODdPK$4$5", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\"[^\n\r\"]*?)(\/\/)(?=([^\n\r]*?\"\s*;))/", "$1qDdXX", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\"[^\n\r\"]*?)(\/\*)(?!([YZ]u\*\/))(?=([^\n\r]*?\"\s*;))/", "$1pDdYX", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\"[^\n\r\"]*?)(\*\/)(?=([^\n\r]*?\"\s*;))/", "$1ODdPK", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\'[^\n\r\']*?)(\/\/)(?=([^\n\r]*?\'\s*;))/", "$1qDdXX", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\'[^\n\r\']*?)(\/\*)(?!([YZ]u\*\/))(?=([^\n\r]*?\'\s*;))/", "$1pDdYX", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(=\s*\'[^\n\r\']*?)(\*\/)(?=([^\n\r]*?\'\s*;))/", "$1ODdPK", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\=|\()(\s*\")([^\r\n\'\"]*?[\'][^\r\n\'\"]*?)(\/)(\/)([^\r\n\'\"]*?[\'])(\s*\'[^\r\n\'\"]*?)(\/\/|qDdXX)?([^\r\n\'\"]*?[\'][^\r\n\'\"]*?[\"])(?!(\'\)|\s*[\)]?\s*\+|\'))/", "$1$2$3qDdXX$6$7qDdXX$9$10", $string, 1, $count);
        } while ($count);
        do {
            $string = preg_replace("/(\=|\()(\s*\')([^\r\n\'\"]*?[\"][^\r\n\'\"]*?)(\/)(\/)([^\r\n\'\"]*?[\"])(\s*\"[^\r\n\'\"]*?)(\/\/|qDdXX)?([^\r\n\'\"]*?[\"][^\r\n\'\"]*?[\'])(?!(\'\)|\s*[\)]?\s*\+|\'))/", "$1$2$3qDdXX$6$7qDdXX$9$10", $string, 1, $count);
        } while ($count);
        $string = preg_replace("/([^\*])(\*|[\r\n]|[^\\\\]\'|[^\\\\]\"|\,|\+|\{|;|\(|\)|\[|\]|\{|\}|\?|[^p|s]:|\&|\%|[^\\\\][a-m-o-u-s-zA-Z]|\||-|=|[0-9])(\s*)(?!([^=\\\\\&\/\"\'\^\*:]))(\/)(\/)+(?!([\r\n\*\+\"]*?([^\r\n]*?\*\/|[^\r\n]*?\"\s*\+|([^\r\n]*?=\";)))).*/", "$1$2$3", $string);
        $string = preg_replace("/(\/\/\*\/)(?!([\r\n\*\+\"]*?([^\r\n]*?\*\/|[^\r\n]*?\"\s*\+|([^\r\n]*?=\";)))).*/", "", $string);
        $string = preg_replace("/(?!([^\n\r]*?[\'\"]))(\s*<!--.*-->)(?!())[^\n\r]*?.*/", "$2$4", $string);
        $string = preg_replace("/([\n\r][^\n\r\*\,\"\']*?)(?=([^\*\,\:\;a-zA-Z\"]*?))(\/)(\/)+(?!([\r\n\*\+\"]*?([^\r\n]*?\*\/|[^\r\n]*?\"\s*\+|([^\r\n]*?=\";)))).*/", "$1", $string);
        $string = preg_replace("/(?!([^\n\r]*?[\'\"]))(\s*<!--.*-->)(?!())[^\n\r]*?(\*\/)?.*/", "", $string);
        $string = preg_replace("/(<!--.*?-->)(?=(\s*))/", "", $string);
        $string = preg_replace("/qDdXX/", "//", $string);
        $string = preg_replace("/pDdYX/", "/*", $string);
        $string = preg_replace("/ODdPK/", "*/", $string);
        $string = preg_replace("/RDdPK/", "*/*", $string);
        $string = preg_replace("/TDdXX/", "//*", $string);
        $string = preg_replace('/WQerT/', '\\\\r\\\\n" +', $string);
        $string = preg_replace('/YQerT/', '//*/\\\\r\\\\n" +', $string);
        $string = preg_replace('/AQerT/', '\\\\n', $string);
        $string = preg_replace('/BQerT/', '\\\\r', $string);
        $string = preg_replace("/ZUQQ/", "/ /", $string);
        $string = preg_replace('/\s\/\*Zu\*\/\s/', '', $string);
        $string = preg_replace('/\s\/\*Yu\*\/\s/', '', $string);
        $string = preg_replace('/(AwTc)/', '', $string);
        $string = preg_replace('/(CwRc)/', '', $string);
        $string = preg_replace("/([a-zA-Z0-9]\s?)\s*[\n\r]+(\s*[\)\,&]\s?)(\s*[\r\n]+\s*[\{])/", "$1$2$3", $string);
        $string = preg_replace("/([a-zA-Z0-9\(]\s?)\s*[\n\r]+(\s*[;\)\,&\+\-a-zA-Z0-9]\s?)(\s*[\{;a-zA-Z0-9\,&\n\r])/", "$1$2$3", $string);
        $string = preg_replace("/(\(\s?)\s*[\n\r]+(\s*function)/", "$1$2", $string);
        $string = preg_replace("/(=\s*\[[a-zA-Z0-9]\s?)\s*([\r\n]+)/", "$1", $string);
        $string = preg_replace("/([^\*\/\'\"]\s*)(\/\/\s*\*\/)/", "$1", $string);
        $string = preg_replace("/(\/\*\*\/)(\/\/(?!([^\n\r]*?\*\/)).*)/", "$1", $string);
        $string = preg_replace("/(\;\/\*\*\/)(?!([^\n\r]*?\*\/)).*/", "", $string);
        $string = preg_replace("/(\/\/\\\\\*[^\n\r\"\'\/]*?[\n\r])/", "\r\n", $string);
        $string = preg_replace("/([\r\n]\s*)(\/\*[^\r\n]*?\*\/(?!([^\r\n]*?\"\s*\+)))/", "$1", $string);
        $string = preg_replace("/\/\*\*\/\s/", " ", $string);
        $string = preg_replace("/(\=\s*)(?=([^\r\n\'\"]*?\'[^\n\r\'\"]*?\'))([^\n\r\/]*?)(\/\/[^\r\n\"\'][^\r\n]*[\'\"])(\/\*\*\/)[\n\r]/", "$1$3$4\n", $string);
        $string = preg_replace("/(\=\s*)(?=([^\r\n\'\"]*?\"[^\n\r\'\"]*?\"))([^\n\r\/]*?)(\/\/[^\r\n\"\'][^\r\n]*[\'\"])(\/\*\*\/)[\n\r]/", "$1$3$4\n", $string);
        $string = preg_replace("/([^\'\"ps\s]\s*)(\:[^\r\n\'\"\[\]]*?\'[^\n\r\'\"]*?\')([^\n\r\/a-zA-Z0-9]*?)(\/\/)[^\r\n\/\'][^\r\n]*/", "$1$2", $string);
        $string = preg_replace("/([^\'\"ps\s]\s*)(\:[^\r\n\'\"\[\]]*?\"[^\n\r\'\"]*?\")([^\n\r\/a-zA-Z0-9]*?)(\/\/)[^\r\n\/\"][^\r\n]*/", "$1$2", $string);
        $string = preg_replace("/(\"[^\n\r\'\"\+]*?\")([^\n\r\/a-zA-Z0-9]*?)(\/\/)(?!(\*|[^\r\n]*?[\\\\n\\\\r]+\s*\"\s*\+|[^\r\n]*?[\\\\n\\\\r]+\s*\'\s*\+))[^\r\n\/\"][^\r\n]*/", "$1$2", $string);
        $string = preg_replace("/(;\s*)\/\/(?!([^\n\r]*?\"\s*;)).*/", "$1 \n", $string);
        $string = preg_replace('/([\n\r][^\n\r\"]*?)([^\/\"\'\*\>])\/\*(?!(\*\/))[^\n\r\"]*?[^@]\*\//', "$1$2", $string);
        $string = preg_replace("/(\|\||[\?]|\,)(\s*)\/\/(?!([^\n\r]*?\*\/|\"|\')).*/", "$1$2", $string);
        $string = preg_replace("/([\|\[\;\,\:\=\-\{\}\]\[\?\)\(])\s*[\n\r]\s*[\n\r](\s*[\n\r])+/", "$1\n", $string);
        //END Remove comments.    //START Remove all whitespaces. Compression!
        $string = preg_replace('/(--\s+\>)/', 'HwRc', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = preg_replace('/\s*(?:(?=[=\-\+\|%&\*\)\[\]\{\};:\,\.\\!\@\#\^`~]))/', '', $string);
        $string = preg_replace('/(?:(?<=[=\-\+\|%&\*\)\[\]\{\};:\,\.\\?\!\@\#\^`~]))\s*/', '', $string);
        $string = preg_replace('/([^a-zA-Z0-9\s\-=+\|!@#$%^&*()`~\[\]{};:\'",\/?])\s+([^a-zA-Z0-9\s\-=+\|!@#$%^&*()`~\[\]{};:\'",\/?])/', '$1$2', $string);
        $string = preg_replace('/(HwRc)/', '-- >', $string);

        return $string;
    }

    public function _htmlMinify($html)
    {

        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        ];

        $replace = [
            '>',
            '<',
            '\\1',
            ''
        ];

        return preg_replace($search, $replace, $html);
    }

    public function dispatchLoopShutdown($body)
    {
        //remove redundant (white-space) characters
        $replace = [
            //remove tabs before and after HTML tags
            '/\>[^\S ]+/s'                                                    => '>',
            '/[^\S ]+\</s'                                                    => '<',
            //shorten multiple whitespace sequences; keep new-line characters because they matter in JS!!!
            '/([\t ])+/s'                                                     => ' ',
            //remove leading and trailing spaces
            '/^([\t ])+/m'                                                    => '',
            '/([\t ])+$/m'                                                    => '',
            // remove JS line comments (simple only); do NOT remove lines containing URL (e.g. 'src="http://server.com/"')!!!
            '~//[a-zA-Z0-9 ]+$~m'                                             => '',
            //remove empty lines (sequence of line-end and white-space characters)
            '/[\r\n]+([\t ]?[\r\n]+)+/s'                                      => "\n",
            //remove empty lines (between HTML tags); cannot remove just any line-end characters because in inline JS they can matter!
            '/\>[\r\n\t ]+\</s'                                               => '><',
            //remove "empty" lines containing only JS's block end character; join with next line (e.g. "}\n}\n</script>" --> "}}</script>"
            '/}[\r\n\t ]+/s'                                                  => '}',
            '/}[\r\n\t ]+,[\r\n\t ]+/s'                                       => '},',
            //remove new-line after JS's function or condition start; join with next line
            '/\)[\r\n\t ]?{[\r\n\t ]+/s'                                      => '){',
            '/,[\r\n\t ]?{[\r\n\t ]+/s'                                       => ',{',
            //remove new-line after JS's line end (only most obvious and safe cases)
            '/\),[\r\n\t ]+/s'                                                => '),',
            //remove quotes from HTML attributes that does not contain spaces; keep quotes around URLs!
            '~([\r\n\t ])?([a-zA-Z0-9]+)="([a-zA-Z0-9_/\\-]+)"([\r\n\t ])?~s' => '$1$2=$3$4', //$1 and $4 insert first white-space character found before/after attribute
        ];
        $body    = preg_replace(array_keys($replace), array_values($replace), $body);

        //remove optional ending tags (see http://www.w3.org/TR/html5/syntax.html#syntax-tag-omission )
        $remove = [
            '</option>', '</li>', '</dt>', '</dd>', '</tr>', '</th>', '</td>'
        ];
        $body   = str_ireplace($remove, '', $body);

        return $body;
    }
}