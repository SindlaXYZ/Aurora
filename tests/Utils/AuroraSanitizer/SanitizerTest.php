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
}

}
