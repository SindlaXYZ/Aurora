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
}

}
