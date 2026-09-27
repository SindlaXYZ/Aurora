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

    public function testIpAddressMatchesOnlyItself(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain('1.2.3.4', '2.3.4'));
        $this->assertFalse($matcher->matchDomain('10.1.2.3', '1.2.3'));
        $this->assertTrue($matcher->matchDomain('1.2.3.4', '1.2.3.4'));
        $this->assertTrue($matcher->matchDomain('http://1.2.3.4/path', '1.2.3.4'));
    }

    public function testEmptyValuesDoNotMatch(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain('', 'example.com'));
        $this->assertFalse($matcher->matchDomain('example.com', ''));
    }
}

