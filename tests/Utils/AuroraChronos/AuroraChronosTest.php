<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Chronos;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraChronos\AuroraChronos;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraChronosTest.php --no-coverage
 */
class AuroraChronosTest extends KernelTestCase
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

    public function testMinutesBetweenTwoDates()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'startDate' => '2010-01-01 11:12:13',
                         'endDate'   => '2010-01-01 11:12:13',
                         'expected'  => 0
                     ],
                     [
                         'startDate' => '2010-01-01 11:12:13',
                         'endDate'   => '2010-01-01 11:13:13',
                         'expected'  => 1
                     ],
                     [
                         'startDate' => '2010-01-01 11:13:13',
                         'endDate'   => '2010-01-01 11:12:13',
                         'expected'  => -1
                     ],
                     [
                         'startDate' => '2010-01-01 00:00:00',
                         'endDate'   => '2010-01-02 23:59:59',
                         'expected'  => 2879
                     ]
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->minutesBetweenTwoDates($test['startDate'], $test['endDate']), json_encode($test));
        }
    }

    public function testDiffIsHigherThan()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:14',
                         'interval'     => 0,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:15',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_SECONDS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 12:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:13:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 11:13:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_MINUTES,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 12:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_HOURS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-01 12:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_HOURS,
                         'expected'     => true
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-08 11:12:13',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_WEEKS,
                         'expected'     => false
                     ],
                     [
                         'startDate'    => '2010-01-01 11:12:13',
                         'endDate'      => '2010-01-08 11:12:14',
                         'interval'     => 1,
                         'intervalUnit' => AuroraChronos::TIME_UNIT_WEEKS,
                         'expected'     => true
                     ],
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->diffIsHigherThan($test['startDate'], $test['endDate'], $test['interval'], $test['intervalUnit']), json_encode($test));
        }
    }

    public function testDateToHuman()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'date'        => '2010-01-01 11:12:13',
                         'humanFormat' => 'Y-m-d',
                         'expected'    => '2010-01-01'
                     ],
                     [
                         'date'        => '2010-12-01 11:12:13',
                         'humanFormat' => 'd/m/Y',
                         'expected'    => '01/12/2010'
                     ],
                     [
                         'date'        => '2010-12-01 11:12:13',
                         'humanFormat' => 'd/m/Y H:i',
                         'expected'    => '01/12/2010 11:12'
                     ],
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->dateToHuman($test['date'], $test['humanFormat']), json_encode($test));
        }
    }

    public function testSecondsBetweenTwoDates()
    {
        $Chronos = new AuroraChronos();

        foreach ([
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:12:13')
                             ],
                         'expected' => 0
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:12:14')
                             ],
                         'expected' => 1
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:13:13')
                             ],
                         'expected' => 60
                     ],
                     [
                         'given'    =>
                             [
                                 'start' => new \DateTimeImmutable('2010-01-01 11:12:13'),
                                 'end'   => new \DateTimeImmutable('2010-01-01 11:13:14')
                             ],
                         'expected' => 61
                     ]
                 ] as $test) {
            $this->assertEquals($test['expected'], $Chronos->secondsBetweenTwoDates($test['given']['start'], $test['given']['end']), json_encode($test));
        }
    }
}
