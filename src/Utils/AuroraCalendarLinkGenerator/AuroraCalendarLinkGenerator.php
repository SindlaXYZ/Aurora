<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCalendarLinkGenerator;

class AuroraCalendarLinkGenerator
{
    public function __construct(
        protected string             $title,
        protected \DateTimeInterface $start,
        protected \DateTimeInterface $end,
        protected string             $description = '',
        protected string             $location = ''
    )
    {
    }

    private function formatDateForGoogle(\DateTimeInterface $date): string
    {
        return $date->format('Ymd\THis');
    }

    private function formatDateForOutlook(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s');
    }

    public function getGoogleCalendarLink(): string
    {
        $startFormatted = $this->formatDateForGoogle($this->start);
        $endFormatted   = $this->formatDateForGoogle($this->end);

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $this->title,
            'dates'    => $startFormatted . '/' . $endFormatted,
            'details'  => $this->description,
            'location' => $this->location
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    public function getYahooCalendarLink(): string
    {
        $startFormatted = $this->formatDateForGoogle($this->start);
        $endFormatted   = $this->formatDateForGoogle($this->end);

        $params = [
            'v'      => 60,
            'title'  => $this->title,
            'st'     => $startFormatted,
            'et'     => $endFormatted,
            'desc'   => $this->description,
            'in_loc' => $this->location
        ];

        return 'https://calendar.yahoo.com/?' . http_build_query($params);
    }

    public function getOutlookLiveCalendarLink(): string
    {
        $startFormatted = $this->formatDateForOutlook($this->start);
        $endFormatted   = $this->formatDateForOutlook($this->end);

        $params = [
            'path'     => '/calendar/action/compose',
            'rru'      => 'addevent',
            'subject'  => $this->title,
            'startdt'  => $startFormatted,
            'enddt'    => $endFormatted,
            'body'     => $this->description,
            'location' => $this->location
        ];

        return 'https://outlook.live.com/owa/?' . http_build_query($params);
    }

    public function getOutlookOfficeCalendarLink(): string
    {
        $startFormatted = $this->formatDateForOutlook($this->start);
        $endFormatted   = $this->formatDateForOutlook($this->end);

        $params = [
            'subject'  => $this->title,
            'body'     => $this->description,
            'startdt'  => $startFormatted,
            'enddt'    => $endFormatted,
            'location' => $this->location
        ];

        return 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }
}
