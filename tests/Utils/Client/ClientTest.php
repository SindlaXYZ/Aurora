<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Client;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Client\Client;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Client/ClientTest.php --no-coverage
 */
class ClientTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    /** @var Client */
    protected $client;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
        $this->client        = $this->containerTest->get('test.client');
    }

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function __SKIP__testIP2CountryCode()
    {
        $Client = new Client($this->containerTest);

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

    public function testIpIsGoogleBot()
    {
        $this->client->request('GET', 'https://www.gstatic.com/ipranges/goog.json');
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $googJson = $this->client->getResponse()->getContent();

        $this->assertFalse(empty(trim($googJson)), 'goog.json is empty');

        $googArray = json_decode($googJson, true);

        $this->assertIsArray($googArray);
        $this->assertArrayHasKey('prefixes', $googArray);

        foreach ($googArray['prefixes'] as $ipv4Prefix) {
            $ipV4Range = $ipv4Prefix['ipv4Prefix'] ?? '';
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