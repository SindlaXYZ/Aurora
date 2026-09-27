<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraMatch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;

class InvalidDomainTest extends TestCase
{
    public function testInvalidUrlInputsDoNotTriggerWarnings(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain(':::', 'example.com'));
        $this->assertFalse($matcher->matchDomain('example.com', ':::'));
    }

    public function testDomainMatchingIsCaseInsensitive(): void
    {
        $matcher = new AuroraMatch();

        $this->assertTrue($matcher->matchDomain('HTTP://WWW.EXAMPLE.COM', 'example.com'));
        $this->assertTrue($matcher->matchDomain('example.com', 'EXAMPLE.COM'));
    }

    #[DataProvider('dataHostsWithInvalidCharactersDoNotMatch')]
    public function testHostsWithInvalidCharactersDoNotMatch(string $needle): void
    {
        // Browsers treat "\" as "/", so "http://evil.com\.example.com" is "evil.com"
        $this->assertFalse(new AuroraMatch()->matchDomain($needle, 'example.com'));
    }

    public static function dataHostsWithInvalidCharactersDoNotMatch(): array
    {
        return [
            ['http://evil.com\\.example.com'],
            ['evil.com\\.example.com'],
            ['http://evil.com%2F.example.com'],
            ["example.com\n"],
        ];
    }

    #[DataProvider('dataBackslashesEndTheHostLikeInBrowsers')]
    public function testBackslashesEndTheHostLikeInBrowsers(string $needle, bool $expected): void
    {
        // parse_url() used to read "example.com" as the host of "https://evil.com\@example.com/" (a browser opens "evil.com")
        $this->assertSame($expected, new AuroraMatch()->matchDomain($needle, 'example.com'));
    }

    public static function dataBackslashesEndTheHostLikeInBrowsers(): array
    {
        return [
            'backslash before the at sign'       => ['https://evil.com\\@example.com/', false],
            'two backslashes before the at sign' => ['https://evil.com\\\\@example.com/', false],
            'protocol relative'                  => ['//evil.com\\@example.com', false],
            'without scheme'                     => ['evil.com\\@example.com', false],
            'backslashes after the scheme'       => ['https:\\\\evil.com\\@example.com', false],
            'backslash in the path'              => ['https://www.example.com\\path\\@evil.com', true],
            'user info'                          => ['https://user@example.com/', true],
        ];
    }

    public function testIpAddressMatchesOnlyItself(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain('1.2.3.4', '2.3.4'));
        $this->assertFalse($matcher->matchDomain('10.1.2.3', '1.2.3'));
        $this->assertTrue($matcher->matchDomain('1.2.3.4', '1.2.3.4'));
        $this->assertTrue($matcher->matchDomain('http://1.2.3.4/path', '1.2.3.4'));

        // A host name ending with the allowed IP address must not match it
        $this->assertFalse($matcher->matchDomain('foo.1.2.3.4', '1.2.3.4'));
        $this->assertFalse($matcher->matchDomain('http://foo.1.2.3.4/', '1.2.3.4'));
    }

    public function testBracketedIPv6Hosts(): void
    {
        $matcher = new AuroraMatch();

        // parse_url() keeps the brackets of IPv6 literals: "http://[2001:db8::1]/" => "[2001:db8::1]"
        $this->assertTrue($matcher->matchDomain('http://[2001:db8::1]/', 'http://[2001:db8::1]/'));
        $this->assertTrue($matcher->matchDomain('http://[2001:db8::1]:8080/path', '2001:db8::1'));
        $this->assertTrue($matcher->matchDomain('[2001:db8::1]', '2001:db8::1'));
        $this->assertFalse($matcher->matchDomain('http://[2001:db8::2]/', '2001:db8::1'));

        // Only IPv6 literals are unbracketed
        $this->assertFalse($matcher->matchDomain('http://[evil.example.com]/', 'example.com'));
    }

    public function testEmptyValuesDoNotMatch(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain('', 'example.com'));
        $this->assertFalse($matcher->matchDomain('example.com', ''));
    }
}

