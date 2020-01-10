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

    /**
     * Fake test. Do not delete this, otherwise Bitbucket Pipeline will fail
     */
    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }


    public function testIp2()
    {
        $Client = new Client($this->containerTest);

        foreach ([
                     [
                         'ip'          => '109.99.91.136',
                         'countryCode' => 'RO'
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
            $this->assertNotNull($Client->ip2CountryCode($ip['ip']));
            //$this->assertNotNull($Client->ip2CityCounty($ip['ip']));
            //$this->assertNotNull($Client->ip2CityName($ip['ip']));
        }
    }

}