<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraClient/AuroraClientTest.php --no-coverage
 */
class AuroraClientTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    /** @var AuroraClient */
    protected $client;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
        $this->client        = $this->containerTest->get('test.client');
    }

    public function testIpIsValidAcceptsIpv6(): void
    {
        $client = new AuroraClient($this->containerTest);

        $this->assertTrue(
            $client->ipIsValid('2001:4860:4860::8888'),
            'Expected a public IPv6 address to be considered valid.'
        );

        $this->assertFalse(
            $client->ipIsValid('::1'),
            'Loopback IPv6 address should be considered invalid.'
        );
    }

    public function testProtocolAndIsSSL(): void
    {
        $client = new AuroraClient($this->containerTest);

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertSame('https://', $client->protocol());
        $this->assertTrue($client->isSSL());
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

        $_SERVER['HTTPS'] = 'on';
        $this->assertSame('https://', $client->protocol());
        $this->assertTrue($client->isSSL());

        $_SERVER['HTTPS'] = 'off';
        $this->assertSame('http://', $client->protocol());
        $this->assertFalse($client->isSSL());

        unset($_SERVER['HTTPS']);
    }

    public function testPreferredLanguagesTrimsSpaces(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US, en;q=0.5,fr;q=0.7';
        $client                          = new AuroraClient($this->containerTest);

        $this->assertSame(
            [
                'en-US' => 1.0,
                'fr'    => 0.7,
                'en'    => 0.5,
            ],
            $client->preferredLanguages()
        );

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testPreferredLanguagesKeepsHighestQualityForDuplicateLocales(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.8,en-US;q=0.4';
        $client                          = new AuroraClient($this->containerTest);

        $this->assertSame(
            [
                'en-US' => 1.0,
                'en'    => 0.8,
            ],
            $client->preferredLanguages()
        );

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testIpMatchesCidrRejectsInvalidPrefixes(): void
    {
        $client = new AuroraClient($this->containerTest);

        $reflectionMethod = new \ReflectionMethod($client, 'ipMatchesCidr');
        $reflectionMethod->setAccessible(true);

        self::assertFalse(
            $reflectionMethod->invoke($client, '198.51.100.23', '198.51.100.0/abc'),
            'CIDR masks containing non-numeric characters must be rejected.'
        );

        self::assertFalse(
            $reflectionMethod->invoke($client, '2001:db8::1', '2001:db8::/64bad'),
            'IPv6 CIDR masks containing non-numeric characters must be rejected.'
        );

        self::assertTrue(
            $reflectionMethod->invoke($client, '198.51.100.23', ' 198.51.100.0/24 '),
            'Valid CIDR masks with surrounding whitespace should still be accepted.'
        );
    }
}
