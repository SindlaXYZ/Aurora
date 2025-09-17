<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraMatch;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;

class MatchCssUrlsTest extends TestCase
{
    public function testRelativeUrlsOnly(): void
    {
        $match = new AuroraMatch();
        $css = 'body{background:url("/img/a.png");} '
            . '.abs{background:url("http://example.com/b.png");} '
            . '.proto{background:url("//cdn.example.com/c.png");}';
        $result = $match->matchCssUrls($css);
        $this->assertSame(['/img/a.png'], $result[1]);
    }

    public function testAllUrlsIncludedWhenNotRestricted(): void
    {
        $match = new AuroraMatch();
        $css = 'body{background:url("/img/a.png");} '
            . '.abs{background:url("http://example.com/b.png");} '
            . '.proto{background:url("//cdn.example.com/c.png");}';
        $result = $match->matchCssUrls($css, false);
        $this->assertSame(['/img/a.png', 'http://example.com/b.png', '//cdn.example.com/c.png'], $result[1]);
    }
}
