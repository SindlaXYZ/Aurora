<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCalendarLinkGenerator;

use Sindla\Bundle\AuroraBundle\Utils\AuroraCalendarLinkGenerator\AuroraCalendarLinkGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraCalendarLinkGenerator/AuroraCalendarLinkGeneratorTest.php --no-coverage
 */
class AuroraCalendarLinkGeneratorTest extends KernelTestCase
{
    private $kernelTest;

    protected function setUp(): void
    {
        $this->kernelTest = self::bootKernel();
    }

    ###################################################################################################################################################################################################

    public function testGoogleCalendarLinkWithDateTime(): void
    {
        $title       = 'Test Event';
        $start       = new \DateTime('2025-04-15 10:00:00', new \DateTimeZone('UTC'));
        $end         = new \DateTime('2025-04-15 11:00:00', new \DateTimeZone('UTC'));
        $description = 'Event Description';
        $location    = 'Test Location';

        $generator = new AuroraCalendarLinkGenerator($title, $start, $end, $description, $location);
        $link      = $generator->getGoogleCalendarLink();

        $utcTimezone   = new \DateTimeZone('UTC');
        $expectedStart = (clone $start)->setTimezone($utcTimezone)->format('Ymd\THis\Z');
        $expectedEnd   = (clone $end)->setTimezone($utcTimezone)->format('Ymd\THis\Z');

        $this->assertStringContainsString('https://calendar.google.com/calendar/render?', $link);
        $this->assertStringContainsString("action=TEMPLATE", $link);
        $this->assertStringContainsString("text=" . urlencode($title), $link);
        $this->assertStringContainsString("dates=" . urlencode($expectedStart . '/' . $expectedEnd), $link);
        $this->assertStringContainsString("details=" . urlencode($description), $link);
        $this->assertStringContainsString("location=" . urlencode($location), $link);
    }

    ###################################################################################################################################################################################################

    public function testYahooCalendarLinkWithDateTimeImmutable(): void
    {
        $title       = 'Test Event';
        $start       = new \DateTimeImmutable('2025-04-15 10:00:00', new \DateTimeZone('UTC'));
        $end         = new \DateTimeImmutable('2025-04-15 11:00:00', new \DateTimeZone('UTC'));
        $description = 'Event Description';
        $location    = 'Test Location';

        $generator = new AuroraCalendarLinkGenerator($title, $start, $end, $description, $location);
        $link      = $generator->getYahooCalendarLink();

        $utcTimezone   = new \DateTimeZone('UTC');
        $expectedStart = (clone $start)->setTimezone($utcTimezone)->format('Ymd\THis\Z');
        $expectedEnd   = (clone $end)->setTimezone($utcTimezone)->format('Ymd\THis\Z');

        $this->assertStringContainsString('https://calendar.yahoo.com/?', $link);
        $this->assertStringContainsString("v=60", $link);
        $this->assertStringContainsString("title=" . urlencode($title), $link);
        $this->assertStringContainsString("st=" . urlencode($expectedStart), $link);
        $this->assertStringContainsString("et=" . urlencode($expectedEnd), $link);
        $this->assertStringContainsString("desc=" . urlencode($description), $link);
        $this->assertStringContainsString("in_loc=" . urlencode($location), $link);
    }

    ###################################################################################################################################################################################################

    public function testWebOutlookLink(): void
    {
        $title       = 'Test Event';
        $start       = new \DateTime('2025-04-15 10:00:00', new \DateTimeZone('UTC'));
        $end         = new \DateTime('2025-04-15 11:00:00', new \DateTimeZone('UTC'));
        $description = 'Event Description';
        $location    = 'Test Location';

        $generator = new AuroraCalendarLinkGenerator($title, $start, $end, $description, $location);
        $link      = $generator->getOutlookLiveCalendarLink();

        $utcTimezone   = new \DateTimeZone('UTC');
        $expectedStart = (clone $start)->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z');
        $expectedEnd   = (clone $end)->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z');

        $this->assertStringContainsString('https://outlook.live.com/owa/?', $link);
        $this->assertStringContainsString("rru=addevent", $link);
        $this->assertStringContainsString("subject=" . urlencode($title), $link);
        $this->assertStringContainsString("startdt=" . urlencode($expectedStart), $link);
        $this->assertStringContainsString("enddt=" . urlencode($expectedEnd), $link);
        $this->assertStringContainsString("body=" . urlencode($description), $link);
        $this->assertStringContainsString("location=" . urlencode($location), $link);
    }

    ###################################################################################################################################################################################################

    public function testWebOfficeLinkWithDateTimeImmutable(): void
    {
        $title       = 'Test Event';
        $start       = new \DateTimeImmutable('2025-04-15 10:00:00', new \DateTimeZone('UTC'));
        $end         = new \DateTimeImmutable('2025-04-15 11:00:00', new \DateTimeZone('UTC'));
        $description = 'Event Description';
        $location    = 'Test Location';

        $generator = new AuroraCalendarLinkGenerator($title, $start, $end, $description, $location);
        $link      = $generator->getOutlookOfficeCalendarLink();

        $utcTimezone   = new \DateTimeZone('UTC');
        $expectedStart = (clone $start)->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z');
        $expectedEnd   = (clone $end)->setTimezone($utcTimezone)->format('Y-m-d\TH:i:s\Z');

        $this->assertStringContainsString('https://outlook.office.com/calendar/0/deeplink/compose?', $link);
        $this->assertStringContainsString("subject=" . urlencode($title), $link);
        $this->assertStringContainsString("startdt=" . urlencode($expectedStart), $link);
        $this->assertStringContainsString("enddt=" . urlencode($expectedEnd), $link);
        $this->assertStringContainsString("body=" . urlencode($description), $link);
        $this->assertStringContainsString("location=" . urlencode($location), $link);
    }

    ###################################################################################################################################################################################################
}
