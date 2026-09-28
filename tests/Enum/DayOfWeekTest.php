<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Enum\DayOfWeek;

class DayOfWeekTest extends TestCase
{
    /**
     * AuroraCalendar compares the values with the ISO-8601 day of the week of a date (format "N": 1 = Monday, 7 = Sunday)
     */
    public function testTheValuesAreTheIso8601DaysOfTheWeek(): void
    {
        $days = [];
        foreach (new \DatePeriod(new \DateTimeImmutable('2024-01-01'), new \DateInterval('P1D'), 6) as $date) {
            $days[strtoupper($date->format('l'))] = DayOfWeek::from((int)$date->format('N'))->name;
        }

        $this->assertCount(7, DayOfWeek::cases());
        $this->assertSame(array_keys($days), array_values($days));
    }
}
