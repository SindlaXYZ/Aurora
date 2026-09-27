<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraMonolog;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Formats incoming records into an HTML table
 *
 * This is especially useful for html email logging
 *
 * @author Tiago Brito <tlfbrito@gmail.com>
 */
class HtmlFormatter extends NormalizerFormatter
{
    /**
     * Translates Monolog log levels to html color priorities.
     *
     * Keyed by the Monolog\Level values: the Symfony\Bridge\Monolog\Logger constants were removed in Symfony 8,
     * so referencing them made the formatter impossible to instantiate.
     */
    protected $logLevels = array(
        Level::Debug->value     => '#cccccc',
        Level::Info->value      => '#468847',
        Level::Notice->value    => '#3a87ad',
        Level::Warning->value   => '#c09853',
        Level::Error->value     => '#f0ad4e',
        Level::Critical->value  => '#FF7708',
        Level::Alert->value     => '#C12A19',
        Level::Emergency->value => '#000000',
    );

    /**
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct($dateFormat = null)
    {
        parent::__construct($dateFormat);
    }

    /**
     * Creates an HTML table row
     *
     * @param  string $th       Row header content
     * @param  string $td       Row standard cell content
     * @param  bool   $escapeTd false if td content must not be html escaped
     * @return string
     */
    protected function addRow($th, $td = ' ', $escapeTd = true)
    {
        $th = htmlspecialchars($th, ENT_NOQUOTES, 'UTF-8');
        if ($escapeTd) {
            $td = '<pre>'.htmlspecialchars($td, ENT_NOQUOTES, 'UTF-8').'</pre>';
        }

        return "<tr style=\"padding: 4px;spacing: 0;text-align: left;\">\n<th style=\"background: #cccccc\" width=\"100px\">$th:</th>\n<td style=\"padding: 4px;spacing: 0;text-align: left;background: #eeeeee\">".$td."</td>\n</tr>";
    }

    /**
     * Create a HTML h1 tag
     *
     * @param  string $title Text to be in the h1
     * @param  int    $level Error level
     * @return string
     */
    protected function addTitle($title, $level)
    {
        $title = htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8');

        return '<h1 style="background: '.$this->logLevels[$level].';color: #ffffff;padding: 5px;" class="monolog-output">'.$title.'</h1>';
    }

    /**
     * Formats a log record.
     */
    public function format(LogRecord $record): string
    {
        $normalizedRecord = parent::format($record);
        if (!\is_array($normalizedRecord)) {
            $normalizedRecord = $record->toArray();
        }

        $levelName = $normalizedRecord['level_name'] ?? $record->level->getName();
        $levelValue = $normalizedRecord['level'] ?? $record->level->value;

        $output = $this->addTitle($levelName, $levelValue);
        $output .= '<table cellspacing="1" width="100%" class="monolog-output">';

        $message = $normalizedRecord['message'] ?? $record->message;
        $output .= $this->addRow('Message', (string) $message);

        $dateTime = $record->datetime->format($this->dateFormat);
        $output .= $this->addRow('Time', $dateTime);

        $channel = $normalizedRecord['channel'] ?? $record->channel;
        $output .= $this->addRow('Channel', (string) $channel);

        $miscData = null;
        if (isset($normalizedRecord['misc']) && \is_array($normalizedRecord['misc'])) {
            $miscData = $normalizedRecord['misc'];
        }

        $extra = $record->extra;
        if (isset($extra['misc']) && \is_array($extra['misc'])) {
            $miscData ??= $extra['misc'];
            unset($extra['misc']);
        }

        if (isset($normalizedRecord['extra']['misc']) && \is_array($normalizedRecord['extra']['misc'])) {
            $miscData ??= $normalizedRecord['extra']['misc'];
        }

        if (\is_array($miscData)) {
            foreach ($miscData as $miscKey => $miscValue) {
                $output .= $this->addRow($miscKey, (string) $miscValue);
            }
        }

        if ($record->context) {
            $embeddedTable = '<table cellspacing="1" width="100%">';
            foreach ($record->context as $key => $value) {
                $embeddedTable .= $this->addRow($key, $this->convertToString($value));
            }
            $embeddedTable .= '</table>';
            $output .= $this->addRow('Context', $embeddedTable, false);
        }

        if ($extra) {
            $embeddedTable = '<table cellspacing="1" width="100%">';
            foreach ($extra as $key => $value) {
                $embeddedTable .= $this->addRow($key, $this->convertToString($value));
            }
            $embeddedTable .= '</table>';
            $output .= $this->addRow('Extra', $embeddedTable, false);
        }

        return $output.'</table>';
    }

    /**
     * Formats a set of log records.
     *
     * @param  LogRecord[] $records A set of records to format
     * @return string The formatted set of records
     */
    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    protected function convertToString($data)
    {
        if (null === $data || is_scalar($data)) {
            return (string) $data;
        }

        return json_encode($this->normalize($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
