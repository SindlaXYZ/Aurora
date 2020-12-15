<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Match;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\Match\Match;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Match/MatchTest.php --no-coverage
 */
class MatchTest extends KernelTestCase
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

    public function testMatchDomain()
    {
        $Match = new Match();

        foreach ([
                     # true ------------------------------------------------------------------------
                     [
                         'needle'   => 'http://sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'https://sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'http://www.sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'https://www.sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'http://sub.domain.sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'www.sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     [
                         'needle'   => 'sub.domain.sindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => true,
                     ],
                     # false ------------------------------------------------------------------------
                     [
                         'needle'   => 'http://sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'https://sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'http://www.sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'https://www.sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'http://sub.domain.sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'www.sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'sub.domain.sindla.com.myscamdomain.info',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     # false ------------------------------------------------------------------------
                     [
                         'needle'   => 'http://notsindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'https://notsindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'notsindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'www.notsindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ],
                     [
                         'needle'   => 'sub.domain.notsindla.com',
                         'domain'   => 'sindla.com',
                         'expected' => false,
                     ]
                 ] as $assertion) {

            if ($assertion['expected']) {
                $this->assertTrue($Match->matchDomain($assertion['needle'], $assertion['domain']), sprintf('%s & %s', $assertion['needle'], $assertion['domain']));
            } else {
                $this->assertFalse($Match->matchDomain($assertion['needle'], $assertion['domain']), sprintf('%s & %s', $assertion['needle'], $assertion['domain']));
            }
        }
    }
}