<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendarLinkGenerator;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendarLinkGenerator\AuroraCalendarLinkGenerator;

class AuroraCalendarLinkGeneratorTimezoneTest extends TestCase
{
    public function testLinksUseUtcTimestamps(): void
    {
        $start = new \DateTimeImmutable('2025-04-15 10:00:00', new \DateTimeZone('America/New_York'));
        $end   = new \DateTimeImmutable('2025-04-15 11:00:00', new \DateTimeZone('America/New_York'));

        $generator = new AuroraCalendarLinkGenerator('Title', $start, $end, 'Description', 'Location');

        $expectedGoogleDates = urlencode('20250415T140000Z/20250415T150000Z');
        self::assertStringContainsString('dates=' . $expectedGoogleDates, $generator->getGoogleCalendarLink());

        $yahooLink = $generator->getYahooCalendarLink();
        self::assertStringContainsString('st=' . urlencode('20250415T140000Z'), $yahooLink);
        self::assertStringContainsString('et=' . urlencode('20250415T150000Z'), $yahooLink);

        $outlookLiveLink = $generator->getOutlookLiveCalendarLink();
        self::assertStringContainsString('startdt=' . urlencode('2025-04-15T14:00:00Z'), $outlookLiveLink);
        self::assertStringContainsString('enddt=' . urlencode('2025-04-15T15:00:00Z'), $outlookLiveLink);

        $outlookOfficeLink = $generator->getOutlookOfficeCalendarLink();
        self::assertStringContainsString('startdt=' . urlencode('2025-04-15T14:00:00Z'), $outlookOfficeLink);
        self::assertStringContainsString('enddt=' . urlencode('2025-04-15T15:00:00Z'), $outlookOfficeLink);
    }
}
