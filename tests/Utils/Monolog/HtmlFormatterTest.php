<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\Monolog\HtmlFormatter;

final class TestableHtmlFormatter extends HtmlFormatter
{
    public function callAddRow(string $th, string $td = ' ', bool $escapeTd = true): string
    {
        return $this->addRow($th, $td, $escapeTd);
    }

    public function callAddTitle(string $title, int $level): string
    {
        return $this->addTitle($title, $level);
    }

    public function callConvertToString(mixed $data): string
    {
        return $this->convertToString($data);
    }
}

class HtmlFormatterTest extends TestCase
{
    public function testFormatGeneratesEscapedTableWithNestedContext(): void
    {
        $formatter = new HtmlFormatter('Y-m-d H:i:s');

        $record = new LogRecord(
            datetime: new DateTimeImmutable('2024-01-02 03:04:05'),
            channel: 'app',
            level: Level::Warning,
            message: 'A <strong>message</strong>',
            context: [
                'payload' => ['nested' => 'value'],
            ],
            extra: [
                'user' => 'alice',
                'misc' => [
                    'Custom' => '<tag>value</tag>',
                ],
            ],
        );

        $html = $formatter->format($record);

        $this->assertStringContainsString('<h1 style="background: #c09853;color: #ffffff;padding: 5px;" class="monolog-output">WARNING</h1>', $html);
        $this->assertStringContainsString('&lt;strong&gt;message&lt;/strong&gt;', $html);
        $this->assertStringContainsString('<th style="background: #cccccc" width="100px">Custom:</th>', $html);
        $this->assertStringContainsString('&lt;tag&gt;value&lt;/tag&gt;', $html);
        $this->assertStringContainsString('"nested": "value"', $html);
        $this->assertStringContainsString('Context', $html);
        $this->assertStringContainsString('Extra', $html);
    }

    public function testAddRowEscapesTableDataByDefault(): void
    {
        $formatter = new TestableHtmlFormatter();

        $row = $formatter->callAddRow('Header', '<em>content</em>');

        $this->assertStringContainsString('&lt;em&gt;content&lt;/em&gt;', $row);
    }

    public function testAddRowAllowsHtmlWhenDisabled(): void
    {
        $formatter = new TestableHtmlFormatter();

        $row = $formatter->callAddRow('Header', '<em>content</em>', false);

        $this->assertStringContainsString('<em>content</em>', $row);
    }

    public function testFormatBatchConcatenatesFormattedMessages(): void
    {
        $formatter = new HtmlFormatter();

        $records = [
            $this->createRecord('First message'),
            $this->createRecord('Second message'),
        ];

        $batchHtml = $formatter->formatBatch($records);

        $this->assertSame(2, substr_count($batchHtml, '<table cellspacing="1" width="100%" class="monolog-output">'));
        $this->assertStringContainsString('First message', $batchHtml);
        $this->assertStringContainsString('Second message', $batchHtml);
    }

    public function testConvertToStringNormalizesStructuredData(): void
    {
        $formatter = new TestableHtmlFormatter();

        $json = $formatter->callConvertToString(['foo' => ['bar' => 'baz']]);
        $this->assertSame("{\n    \"foo\": {\n        \"bar\": \"baz\"\n    }\n}", $json);

        $this->assertSame('', $formatter->callConvertToString(null));
        $this->assertSame('42', $formatter->callConvertToString(42));
    }

    private function createRecord(string $message): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: $message,
        );
    }
}
