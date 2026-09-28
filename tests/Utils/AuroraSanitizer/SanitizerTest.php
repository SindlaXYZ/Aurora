<?php

namespace MatthiasMullie\Minify {
    class CSS
    {
        /** @var list<string> */
        public array $added = [];

        public function add($data): void
        {
            $this->added[] = (string) $data;
        }

        public function minify(): string
        {
            return implode('', $this->added);
        }
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraSanitizer {

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraSanitizer\AuroraSanitizer;

class SanitizerTest extends TestCase
{
    public function testCssClearCommentsRemovesBlockAndLineComments(): void
    {
        $sanitizer = new AuroraSanitizer();

        $css = "   \n/* comment */\nbody { color: red; }\n// inline comment\n";

        $result = $sanitizer->cssClearComments($css);

        self::assertSame('body { color: red; }', trim($result));
        self::assertStringNotContainsString('/*', $result);
        self::assertStringNotContainsString('//', $result);
    }

    public function testCssMinifyRewritesRelativeUrlsUsingAssetDirectory(): void
    {
        $sanitizer = new AuroraSanitizer();

        $css = <<<CSS
.foo {
    background: url(images/bg.png);
    mask: url('icons/icon.svg');
    border-image: url("https://example.com/img.png");
}
CSS;

        $result = $sanitizer->cssMinify($css, '/assets/css/styles.css');

        self::assertStringContainsString('url(/assets/css/images/bg.png)', $result);
        self::assertStringContainsString("url('/assets/css/icons/icon.svg')", $result);
        self::assertStringContainsString('url("https://example.com/img.png")', $result);
        self::assertStringNotContainsString('/*', $result);
    }

    public function testHtmlMinifyMinifiesInlineCssAndJavascriptBlocks(): void
    {
        $sanitizer = new class extends AuroraSanitizer {
            /** @var list<string> */
            public array $cssInputs = [];

            /** @var list<array{code: string, removeConsoleOutputs: bool}> */
            public array $jsInputs = [];

            public function minifyCSS($input)
            {
                $this->cssInputs[] = $input;

                return 'css:' . trim($input);
            }

            public function minifyJS($input, $removeConsoleOutputs = false)
            {
                $this->jsInputs[] = [
                    'code' => $input,
                    'removeConsoleOutputs' => $removeConsoleOutputs,
                ];

                return 'js:' . trim($input);
            }
        };

        $html = '<div style="color: red; " data-test="value">Test<style>.foo { color: red; }</style><script>console.log("x");</script></div>';

        $result = $sanitizer->htmlMinify($html);

        self::assertSame(
            ['color: red; ', '.foo { color: red; }'],
            $sanitizer->cssInputs
        );
        self::assertSame(
            [['code' => 'console.log("x");', 'removeConsoleOutputs' => false]],
            $sanitizer->jsInputs
        );

        self::assertStringContainsString('style="css:color: red;"', $result);
        self::assertStringContainsString('<style>css:.foo { color: red; }</style>', $result);
        self::assertStringContainsString('<script>js:console.log("x");</script>', $result);
    }

    public function testMinifyHtmlCollapsesTheWhitespaceBetweenTags(): void
    {
        $html = "<div>\n    <p>Aurora   bundle</p>\n    <!-- comment -->\n    <a  href=\"/x\" >x</a>\n</div>";

        self::assertSame('<div><p>Aurora bundle</p><a href="/x">x</a></div>', new AuroraSanitizer()->minifyHTML($html));
    }

    /**
     * The whitespace between two inline elements is a visible space: "<b>Hello</b> <i>world</i>" used to be displayed "Helloworld"
     */
    #[DataProvider('dataMinifyHtmlKeepsTheSpaceBetweenInlineElements')]
    public function testMinifyHtmlKeepsTheSpaceBetweenInlineElements(string $html, string $expected): void
    {
        self::assertSame($expected, new AuroraSanitizer()->minifyHTML($html));
    }

    public static function dataMinifyHtmlKeepsTheSpaceBetweenInlineElements(): array
    {
        return [
            'inline elements'              => ['<p><b>Hello</b> <i>world</i></p>', '<p><b>Hello</b> <i>world</i></p>'],
            'links on their own lines'     => [
                "<p>\n    <a href=\"/terms\">Terms</a>\n    <a href=\"/privacy\">Privacy</a>\n</p>",
                '<p><a href="/terms">Terms</a> <a href="/privacy">Privacy</a></p>',
            ],
            'a label and its input'        => ["<label>Name</label>\n<input name=\"name\">", '<label>Name</label> <input name="name">'],
            'a label and its textarea'     => ["<label>Message</label>\n<textarea>a\n b</textarea>", "<label>Message</label> <textarea>a\n b</textarea>"],
            'block elements'               => ["<ul>\n    <li>a</li>\n    <li>b</li>\n</ul>", '<ul><li>a</li><li>b</li></ul>'],
            'the document head'            => [
                "<!DOCTYPE html>\n<html>\n<head>\n    <title>x</title>\n    <meta charset=\"utf-8\">\n</head>",
                '<!DOCTYPE html><html><head><title>x</title><meta charset="utf-8"></head>',
            ],
            'an inline element in a block' => ["<div>\n    <span>a</span>\n</div>", '<div><span>a</span></div>'],
        ];
    }

    /**
     * Joining the lines used to turn a "// comment" into a comment of the whole rest of the inline script
     * (every second consecutive "//" line and every trailing "//" comment were left in place)
     */
    public function testMinifyHtmlKeepsInlineScriptsWorking(): void
    {
        $script = "<script>\n    // first comment\n    // second comment\n    foo();\n    var a = 1; // trailing comment\n    bar();\n</script>";

        $result = new AuroraSanitizer()->minifyHTML("<body>\n    <p>Aurora</p>\n    {$script}\n</body>");

        self::assertSame("<body><p>Aurora</p>{$script}</body>", $result);
    }

    /**
     * The text displayed by <pre> and the value submitted by <textarea> used to lose their new lines and spaces
     */
    public function testMinifyHtmlKeepsPreformattedTextAndTextareaValues(): void
    {
        $pre      = "<pre class=\"code\">line 1\n    line 2</pre>";
        $textarea = "<TEXTAREA name=\"message\">Hello,\n\n  World</TEXTAREA>";

        $result = new AuroraSanitizer()->minifyHTML("<div>\n    {$pre}\n    {$textarea}\n</div>");

        self::assertSame("<div>{$pre}{$textarea}</div>", $result);
    }

    /**
     * A PCRE failure (e.g. the backtrack limit, reached by a large page with an unclosed "<!--") used to return an empty page
     */
    public function testMinifyHtmlReturnsTheOriginalPageWhenTheMinificationFails(): void
    {
        $html = "<body>\n    <p>Aurora</p>\n    <!-- unclosed comment\n" . str_repeat("    <div>text</div>\n", 500) . '</body>';

        $backtrackLimit = ini_get('pcre.backtrack_limit');
        $jit            = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '1000');
        ini_set('pcre.jit', '0');

        try {
            $result = new AuroraSanitizer()->minifyHTML($html);
        } finally {
            ini_set('pcre.backtrack_limit', (string)$backtrackLimit);
            ini_set('pcre.jit', (string)$jit);
        }

        self::assertSame($html, $result);
    }

    /**
     * The <script>, <pre> and <textarea> protection is a PCRE too: an unclosed "<script>" on a large page reaches the backtrack limit
     */
    public function testMinifyHtmlReturnsTheOriginalPageWhenProtectingTheScriptsFails(): void
    {
        $html = "<body>\n    <p>Aurora</p>\n    <script>\n" . str_repeat("    var a = 1;\n", 500) . '</body>';

        $backtrackLimit = ini_get('pcre.backtrack_limit');
        $jit            = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '1000');
        ini_set('pcre.jit', '0');

        try {
            $result = new AuroraSanitizer()->minifyHTML($html);
        } finally {
            ini_set('pcre.backtrack_limit', (string)$backtrackLimit);
            ini_set('pcre.jit', (string)$jit);
        }

        self::assertSame($html, $result);
    }

    public function testCssMinifyKeepsTheDoubleQuotesOfARewrittenUrl(): void
    {
        $result = new AuroraSanitizer()->cssMinify('@font-face { src: url("fonts/aurora.woff2"); }', '/assets/css/fonts.css');

        self::assertMatchesRegularExpression('#url\(("?)/assets/css/fonts/aurora\.woff2\1\)#', $result);
    }

    #[DataProvider('dataMinifyCss')]
    public function testMinifyCss(string $css, string $expected): void
    {
        self::assertSame($expected, new AuroraSanitizer()->minifyCSS($css));
    }

    public static function dataMinifyCss(): array
    {
        return [
            'comments, whitespace, empty rules and redundant values' => [
                "/* comment */\nbody {\n    color : #ffffff;\n    margin: 0px 0px 0px 0px;\n    padding: 0.5em;\n    border: none;\n}\n.empty {}\n"
                . "a { font-family: 'Arial'; background: url(\"img.png\"); }",
                'body{color:#fff;margin:0;padding:.5em;border:0}a{font-family:Arial;background:url(img.png)}',
            ],
            'combinators and attribute selectors'                    => [
                "a > b + c ~ d { margin : 0 auto ; }\n\n\ninput[type = \"text\"] { border: 1px solid #000000; }",
                'a>b+c~d{margin:0 auto}input[type=text]{border:1px solid #000}',
            ],
            'important comments and declarations are kept'          => [
                '/*! license */ a { width: 10px !important ; }',
                '/*! license */ a{width:10px!important}',
            ],
            'strings are kept as they are'                           => [
                'a { content: "a  /* b */  c"; }',
                'a{content:"a  /* b */  c"}',
            ],
            'background position and leading zeros'                  => [
                'a { background-position: 0; opacity: 0.60; margin: -0.5em 0.25em; }',
                'a{background-position:0 0;opacity:.60;margin:-.5em .25em}',
            ],
            'blank input'                                            => ["  \n", "  \n"],
        ];
    }

    #[DataProvider('dataMinifyJs')]
    public function testMinifyJs(string $javascript, string $expected): void
    {
        self::assertSame($expected, new AuroraSanitizer()->minifyJS($javascript));
    }

    public static function dataMinifyJs(): array
    {
        return [
            'line comments and whitespace'           => ["function add(x, y) {\n    // sum\n    return x + y;\n}\n", 'function add(x,y){return x+y;}'],
            'block comments'                         => ["var a = 1; /* one */\nvar b = 2;", 'var a=1;var b=2;'],
            'a "//" in a string is not a comment'    => ['var s = "a // b";', 'var s="a // b";'],
            'a regular expression literal'           => ["var re = /ab+c/g; // re\nvar z = 3;", 'var re=/ab+c/g;var z=3;'],
            'an escaped quote in a string'           => ["var s = 'it\\'s'; // q\nvar t = 1;", "var s='it\\'s';var t=1;"],
            'objects, arrays and conditional blocks' => ["var o = { 'key' : [1, 2] };\nif (o) {\n  b();\n}\nelse {\n  c();\n}", "var o={'key':[1,2]};if (o){b();}else{c();}"],
            'blank input'                            => ['   ', '   '],
        ];
    }

    public function testMinifyJsRemovesTheConsoleCallsOnlyOnDemand(): void
    {
        $javascript = "var a = 1;\nconsole.log(a);\nconsole.warn('x');\nvar b = 2;\n";
        $sanitizer  = new AuroraSanitizer();

        self::assertSame("var a=1;console.log(a);console.warn('x');var b=2;", $sanitizer->minifyJS($javascript));
        self::assertSame('var a=1;var b=2;', $sanitizer->minifyJS($javascript, true));
    }

    #[DataProvider('dataMinifyJsV1')]
    public function testMinifyJsV1(string $javascript, string $expected): void
    {
        $sanitizer = new AuroraSanitizer();

        self::assertSame($expected, new \ReflectionMethod($sanitizer, 'minifyJSV1')->invoke($sanitizer, $javascript));
    }

    public static function dataMinifyJsV1(): array
    {
        return [
            'comments, whitespace and quoted property names' => [
                "var a = 1; // comment\n/* block */\nfunction f ( x ) {\n    return { 'foo' : x };\n}\nvar b = obj['bar'];\n",
                'var a=1;function f(x){return{foo:x}}var b=obj.bar;',
            ],
            'a "//" in a string is not a comment'            => ["var s = \"a // b\"; // c\nvar t = 'x';", "var s=\"a // b\";var t='x';"],
            'blank input'                                    => ['  ', '  '],
        ];
    }

    #[DataProvider('dataMinifyHtmlV1')]
    public function testMinifyHtmlV1(string $html, string $expected): void
    {
        $sanitizer = new AuroraSanitizer();

        self::assertSame($expected, new \ReflectionMethod($sanitizer, 'minifyHTMLV1')->invoke($sanitizer, $html));
    }

    public static function dataMinifyHtmlV1(): array
    {
        return [
            'whitespace around the tags and inside the text' => ["<div>\n\t<p>Hello \t  world</p>\n</div>", '<div><p>Hello world</p></div>'],
            'comments'                                       => ["<p>a</p>\n<!-- x -->\n<p>b</p>", '<p>a</p><p>b</p>'],
        ];
    }

    #[DataProvider('dataUnderscoreHtmlMinify')]
    public function testUnderscoreHtmlMinify(string $html, string $expected): void
    {
        self::assertSame($expected, new AuroraSanitizer()->_htmlMinify($html));
    }

    public static function dataUnderscoreHtmlMinify(): array
    {
        return [
            // Only the line breaks and tabs next to a tag are removed: a space may be displayed
            'whitespace'         => ["<div>\n    <p>Hello   world</p>\n</div>", '<div> <p>Hello world</p></div>'],
            'multiline comments' => ["<p>a</p>\n<!-- x\n y -->\n<p>b</p>", '<p>a</p><p>b</p>'],
        ];
    }

    #[DataProvider('dataDispatchLoopShutdown')]
    public function testDispatchLoopShutdown(string $html, string $expected): void
    {
        self::assertSame($expected, new AuroraSanitizer()->dispatchLoopShutdown($html));
    }

    public static function dataDispatchLoopShutdown(): array
    {
        return [
            'optional end tags and simple attribute values' => [
                "<dl>\n  <dt>Term</dt>\n  <dd>Definition</dd>\n</dl>\n<select><option value=\"a\">A</option></select>\n"
                . '<table><tr><th>H</th></tr><tr><td>D</td></tr></table>',
                '<dl><dt>Term<dd>Definition</dl><select><option value=a>A</select><table><tr><th>H<tr><td>D</table>',
            ],
            'the quotes of an URL are kept'                 => [
                "<ul>\n    <li class=\"item\">One</li>\n\n    <li data-url=\"http://example.com/\">Two</li>\n</ul>",
                '<ul><li class=item>One<li data-url="http://example.com/">Two</ul>',
            ],
            // The new lines matter in JavaScript: only the simple comments and the new lines after a block are removed
            'inline script'                                 => [
                "<script>\n    var a = 1; // note\n    if (a) {\n        a++;\n    }\n    f(a),\n    g();\n</script>",
                "<script> var a = 1; \nif (a){a++;\n}f(a),g();</script>",
            ],
        ];
    }
}

}
