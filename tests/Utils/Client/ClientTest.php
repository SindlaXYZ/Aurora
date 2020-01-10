<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Twig;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Client\Client;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Client/ClientTest.php --no-coverage
 */
class ClientTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testIP2CountryCode()
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
                         'countryCode' => 'US',
                         'county'      => 'California'
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
}