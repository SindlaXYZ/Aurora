<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Client;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Diacritics\Diacritics;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Diacritics/DiacriticsTest.php --no-coverage
 */
class DiacriticsTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testRomanian()
    {
        $Diacritics = new Diacritics();
        $Diacritics->useRomanian();

        foreach ([
                     'Lorem ipsum si dolor sit amet'                                                                                                => 'Lorem ipsum și dolor sit amet',
                     'Extractia gazelor naturale'                                                                                                   => 'Extracția gazelor naturale',
                     '0620 - Extractia gazelor naturale'                                                                                            => '0620 - Extracția gazelor naturale',
                     'Extractia pietrei ornamentale si a pietrei pentru constructii, extractia pietrei calcaroase, ghipsului, cretei si a ardeziei' => 'Extracția pietrei ornamentale și a pietrei pentru construcții, extracția pietrei calcaroase, ghipsului, cretei și a ardeziei',
                     'Instalarea mașinilor si echipamentelor industriale'                                                                           => 'Instalarea mașinilor și echipamentelor industrialea',
                     'Intermedieri in comertul cu masini, echipamente industriale, nave si avioane'                                                 => 'Intermedieri in comerțul cu mașini, echipamente industriale, nave și avioane' // 'in' not captured
                 ] as $given => $expected) {
            $this->assertEquals($expected, $Diacritics->modify($given));
        }
    }
}