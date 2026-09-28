<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Timestampable;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableAvailableInterval;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Timestampable\TimestampableSuspendedInterval;

/**
 * TimestampableAvailableInterval and TimestampableSuspendedInterval: a [from, to] interval, either end may be open (null)
 */
class TimestampableIntervalTest extends TestCase
{
    private const int DAY = 86400;

    /**
     * @param int|null $from Days from now, null for an open start
     * @param int|null $to   Days from now, null for an open end
     */
    #[DataProvider('dataIntervals')]
    public function testIsInTheInterval(string $interval, ?int $from, ?int $to, bool $expected, bool $expectedInTheFuture): void
    {
        $entity = $this->entity($interval, $from, $to);

        $this->assertSame($expected, $entity->{sprintf('is%s', $interval)}());
        $this->assertSame($expectedInTheFuture, $entity->{sprintf('get%sInTheFuture', $interval)}());
    }

    public static function dataIntervals(): iterable
    {
        $intervals = [
            'started, open end'     => [-10, null, true, false],
            'not started yet'       => [10, null, false, true],
            'open start, not ended' => [null, 10, true, false],
            'open start, ended'     => [null, -10, false, false],
            'started, not ended'    => [-10, 10, true, false],
            'ended'                 => [-20, -10, false, false],
            'in the future'         => [10, 20, false, true],
        ];

        foreach (['Available', 'Suspended'] as $interval) {
            foreach ($intervals as $name => $data) {
                yield sprintf('%s, %s', strtolower($interval), $name) => [$interval, ...$data];
            }
        }

        yield 'suspended, no interval' => ['Suspended', null, null, false, false];
    }

    /**
     * @param int|null $from Days from now, null for an open start
     * @param int|null $to   Days from now, null for an open end
     */
    #[DataProvider('dataLifespans')]
    public function testLifespan(string $interval, ?int $from, ?int $to, int $expectedDays): void
    {
        $now    = time();
        $entity = $this->entity($interval, $from, $to);

        $seconds = $entity->{sprintf('get%sAtLifespanAsSeconds', $interval)}();

        // A second may have elapsed since the interval was set: the time since the start grows, the time until the end shrinks
        if (0 === $expectedDays) {
            $this->assertSame(0, $seconds);
        } else if (null !== $from) {
            $this->assertGreaterThanOrEqual($expectedDays * self::DAY, $seconds);
            $this->assertLessThanOrEqual($expectedDays * self::DAY + time() - $now, $seconds);
        } else {
            $this->assertLessThanOrEqual($expectedDays * self::DAY, $seconds);
            $this->assertGreaterThanOrEqual($expectedDays * self::DAY - (time() - $now), $seconds);
        }

        $this->assertSame(
            [$expectedDays * 1440, $expectedDays * 24, $expectedDays],
            [
                $entity->{sprintf('get%sAtLifespanAsMinutes', $interval)}(),
                $entity->{sprintf('get%sAtLifespanAsHours', $interval)}(),
                $entity->{sprintf('get%sAtLifespanAsDays', $interval)}(),
            ]
        );
    }

    public static function dataLifespans(): iterable
    {
        $lifespans = [
            'since the start, open end' => [-10, null, 10],
            'until the end, open start' => [null, 10, 10],
            'not started yet'           => [10, null, 0],
            'ended, open start'         => [null, -10, 0],
            'no interval'               => [null, null, 0],
        ];

        foreach (['Available', 'Suspended'] as $interval) {
            foreach ($lifespans as $name => $data) {
                yield sprintf('%s, %s', strtolower($interval), $name) => [$interval, ...$data];
            }
        }
    }

    private function entity(string $interval, ?int $from, ?int $to): object
    {
        $entity = 'Available' === $interval
            ? new class {
                use TimestampableAvailableInterval;
            }
            : new class {
                use TimestampableSuspendedInterval;
            };

        $date = static fn(?int $days): ?\DateTimeImmutable => null === $days ? null : new \DateTimeImmutable(sprintf('@%d', time() + $days * self::DAY));

        return $entity->{sprintf('set%sFrom', $interval)}($date($from))->{sprintf('set%sTo', $interval)}($date($to));
    }
}
