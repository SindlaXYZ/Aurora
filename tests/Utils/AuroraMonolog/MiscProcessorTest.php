<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraMonolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMonolog\MiscProcessor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class MiscProcessorTest extends TestCase
{
    private array $originalServer = [];

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    public function testAddsTheClientDetailsToTheRecordExtra(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_USER_AGENT' => 'AuroraTest']));

        // Monolog 3 processors receive and return a LogRecord (not an array) and AuroraClient::ip() no longer exists
        $processor = new MiscProcessor($this->createContainer(), $requestStack);
        $record    = $processor($this->createRecord());

        $this->assertSame(
            ['IP' => '198.51.100.9', 'Country' => null, 'U/A' => 'AuroraTest'],
            $record->extra['misc']
        );
    }

    public function testRecordIsUnchangedWithoutRequest(): void
    {
        $processor = new MiscProcessor($this->createContainer(), new RequestStack());
        $record    = $processor($this->createRecord());

        $this->assertSame([], $record->extra);
    }

    public function testExtraAddsTheRemoteAddressAndTheClientIp(): void
    {
        // The proxy of the application
        $_SERVER['REMOTE_ADDR'] = '192.0.2.10';

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.9']));

        $processor = new MiscProcessor($this->createContainer(), $requestStack);
        $processor($this->createRecord());
        $record = $processor->extra();

        $this->assertSame('192.0.2.10', $record->extra['request_ip']);
        $this->assertSame('198.51.100.9', $record->extra['client_ip']);
    }

    public function testExtraWithoutRequestNorRemoteAddress(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        $processor = new MiscProcessor($this->createContainer(), new RequestStack());
        $processor($this->createRecord());

        $this->assertSame(['request_ip' => 'unavailable', 'client_ip' => 'unavailable'], $processor->extra()->extra);
    }

    public function testExtraOfARequestWithoutClientIp(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        // e.g. a sub-request created with "new Request()": Request::getClientIp() is null
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $processor = new MiscProcessor($this->createContainer(), $requestStack);
        $processor($this->createRecord());
        $record = $processor->extra();

        $this->assertSame('unavailable', $record->extra['request_ip']);
        $this->assertSame('unavailable', $record->extra['client_ip']);
    }

    private function createContainer(): Container
    {
        $container = new Container();
        // No GeoLite2 database in this directory: the country lookup is disabled (null)
        $container->setParameter('aurora.resources', sys_get_temp_dir() . '/aurora-misc-processor-test-' . bin2hex(random_bytes(4)));

        return $container;
    }

    private function createRecord(): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable('2024-01-02 03:04:05'),
            channel: 'app',
            level: Level::Info,
            message: 'Aurora',
        );
    }
}
