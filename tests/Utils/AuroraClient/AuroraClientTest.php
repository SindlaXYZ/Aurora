<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraClient/AuroraClientTest.php --no-coverage
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

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
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

    public function __SKIP__testIP2CountryCode()
    {
        $Client = new AuroraClient($this->containerTest);

        foreach ([
                     [
                         'ip'          => '109.99.91.136',
                         'countryCode' => 'RO',
                         'county'      => 'Bucuresti',
                         'city'        => 'Bucharest'
                     ],
                     [
                         'ip'          => '46.97.168.231',
                         'countryCode' => 'RO'
                     ],
                     [
                         'ip'          => '209.85.238.26',
                         'countryCode' => 'US'
                     ],
                     [
                         'ip'          => '66.249.73.154',
                         'countryCode' => 'US'
                     ]
                 ] as $ip) {
            $this->assertEquals($ip['countryCode'], $Client->ip2CountryCode($ip['ip']));

            if (isset($ip['county'])) {
                $this->assertEquals($ip['county'], $Client->ip2CityCounty($ip['ip']));
            }

            if (isset($ip['city'])) {
                $this->assertEquals($ip['city'], $Client->ip2CityName($ip['ip']));
            }
        }
    }

    public function testIpIsGoogleBot(): void
    {
        $auroraClient = new AuroraClient($this->containerTest);

        $ip = '66.249.66.128';
        $this->assertTrue($auroraClient->ipIsGoogleBot($ip),
            sprintf('IP: %s / Host: %s', $ip, gethostbyaddr($ip))
        );

        $ip = '66.249.69.160';
        $this->assertTrue(
            $auroraClient->ipIsGoogleBot($ip),
            sprintf('IP: %s / Host: %s', $ip, gethostbyaddr($ip))
        );

        if (false) {
            // BUG: this will test only internal urls (will remove the host from the request)
            // $this->client->request('GET', 'https://www.gstatic.com/ipranges/goog.json');

            $httpClient = HttpClient::create();
            $response   = $httpClient->request('GET', 'https://www.gstatic.com/ipranges/goog.json');

            // fwrite(STDERR, print_r($response, TRUE));

            $googJson = $response->getContent();

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertFalse(empty(trim($googJson)), 'goog.json is empty');

            $googArray = json_decode($googJson, true);

            $this->assertIsArray($googArray);
            $this->assertArrayHasKey('prefixes', $googArray);

            $googArray['prefixes'] = [
                ['ipv4Prefix' => '64.18.0.0/20'],
                ['ipv4Prefix' => '72.14.192.0/18'],
                ['ipv4Prefix' => '74.125.0.0/16'],
                ['ipv4Prefix' => '108.177.8.0/21'],
                ['ipv4Prefix' => '172.217.0.0/19']
            ];

            foreach ($googArray['prefixes'] as $ipv4Prefix) {
                if ($ipV4CIDR = $ipv4Prefix['ipv4Prefix'] ?? null) {
                    [$net, $mask] = explode('/', $ipV4CIDR);

                    $ipsCount = 1 << (32 - $mask);
                    $start    = ip2long($net);
                    $ips      = [];

                    for ($i = 0; $i < $ipsCount; $i++) {
                        $ips[] = long2ip($start + $i);
                    }

                    $this->assertTrue($auroraClient->ipIsGoogleBot(current($ips)), current($ips));
                    $this->assertTrue($auroraClient->ipIsGoogleBot(end($ips)), end($ips));
                }
            }


            foreach ([
                         // ###########################################################################
                         [
                             'host'     => 'fakegoogle.com',
                             'expected' => false
                         ],
                         [
                             'host'     => 'google.com.ro',
                             'expected' => false
                         ],
                         [
                             'host'     => 'crawl-66-249-66-1.fakegoogle.com',
                             'expected' => false
                         ],
                         [
                             'host'     => 'crawl-66-249-66-1.google.com.ro',
                             'expected' => false
                         ],
                         // ---------------------------------------------------------------------------
                         [
                             'host'     => 'fakegooglebot.com',
                             'expected' => false
                         ],
                         [
                             'host'     => 'googlebot.com.ro',
                             'expected' => false
                         ],
                         [
                             'host'     => 'crawl-66-249-66-1.fakegooglebot.com',
                             'expected' => false
                         ],
                         [
                             'host'     => 'crawl-66-249-66-1.googlebot.com.ro',
                             'expected' => false
                         ],
                     ] as $agent) {
            }
        }
    }
}
