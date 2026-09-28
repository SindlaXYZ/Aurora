<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Component\DependencyInjection\Container;

/**
 * Regression test for https://github.com/SindlaXYZ/Aurora/issues/1
 *
 * The GeoLite2 *.mmdb databases are optional (auto-downloaded by the Composer hooks only when the MaxMind
 * env vars are set); when they are missing, the IP lookups must degrade gracefully (null / empty array)
 * instead of throwing and breaking e.g. a Twig template rendering.
 *
 * The lookups themselves are tested against tiny MaxMind DB files written by the test (a single 192.0.2.0/24 network),
 * the real GeoLite2 databases are not available offline.
 */
class AuroraClientGeoLite2Test extends TestCase
{
    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            foreach (glob($directory . '/maxmind-geoip2/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($directory . '/maxmind-geoip2');
            @rmdir($directory);
        }

        $this->directories = [];
    }

    public function testIpLookupsDegradeGracefullyWhenGeoLite2DatabasesAreMissing(): void
    {
        $resourcesDirectory = sys_get_temp_dir() . '/aurora_geolite2_' . uniqid('', true);
        self::assertTrue(mkdir($resourcesDirectory));

        $container = new Container();
        $container->setParameter('aurora.resources', $resourcesDirectory);

        $client = new AuroraClient($container);

        try {
            self::assertNull($client->ip2CountryCode('8.8.8.8'));
            self::assertNull($client->ip2CityCounty('8.8.8.8'));
            self::assertNull($client->ip2CityName('8.8.8.8'));
            self::assertSame([], $client->ip2ASN('8.8.8.8'));
        } finally {
            @rmdir($resourcesDirectory);
        }
    }

    #[DataProvider('dataIpLookupMethods')]
    public function testIpLookupsRequireTheContainer(string $method): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Container not set/initialized!');

        new AuroraClient()->{$method}('192.0.2.10');
    }

    public static function dataIpLookupMethods(): iterable
    {
        yield 'country code' => ['ip2CountryCode'];
        yield 'county'       => ['ip2CityCounty'];
        yield 'city name'    => ['ip2CityName'];
        yield 'ASN'          => ['ip2ASN'];
    }

    public function testIp2CountryCodeReadsTheGeoLite2CountryDatabase(): void
    {
        $resourcesDirectory = $this->createResourcesDirectory();
        $databaseFile       = $this->writeDatabase($resourcesDirectory, 'GeoLite2Country.mmdb', 'GeoLite2-Country', [
            'country' => ['iso_code' => 'NL', 'names' => ['en' => 'Netherlands']],
        ]);

        $client = new AuroraClient($this->createContainer($resourcesDirectory));

        self::assertSame('NL', $client->ip2CountryCode('192.0.2.10'));
        // Not in the database: AddressNotFoundException is swallowed
        self::assertNull($client->ip2CountryCode('198.51.100.10'));

        // The reader is opened once and reused by the next lookups
        self::assertTrue(unlink($databaseFile));
        self::assertSame('NL', $client->ip2CountryCode('192.0.2.200'));
    }

    public function testIp2CityCountyAndIp2CityNameReadTheGeoLite2CityDatabase(): void
    {
        $resourcesDirectory = $this->createResourcesDirectory();
        $this->writeDatabase($resourcesDirectory, 'GeoLite2City.mmdb', 'GeoLite2-City', [
            'city'         => ['names' => ['en' => 'Example City']],
            'country'      => ['iso_code' => 'NL'],
            'subdivisions' => [['iso_code' => 'EX', 'names' => ['en' => 'Example County']]],
        ]);

        $client = new AuroraClient($this->createContainer($resourcesDirectory));

        self::assertSame('Example County', $client->ip2CityCounty('192.0.2.10'));
        self::assertSame('Example City', $client->ip2CityName('192.0.2.10'));
        self::assertNull($client->ip2CityCounty('198.51.100.10'));
        self::assertNull($client->ip2CityName('198.51.100.10'));
    }

    public function testIp2CityCountyIsNullWhenTheRecordHasNoSubdivision(): void
    {
        $resourcesDirectory = $this->createResourcesDirectory();
        $this->writeDatabase($resourcesDirectory, 'GeoLite2City.mmdb', 'GeoLite2-City', [
            'city' => ['names' => ['en' => 'Example City']],
        ]);

        $client = new AuroraClient($this->createContainer($resourcesDirectory));

        self::assertNull($client->ip2CityCounty('192.0.2.10'));
        self::assertSame('Example City', $client->ip2CityName('192.0.2.10'));
    }

    public function testIp2ASNReadsTheGeoLite2ASNDatabase(): void
    {
        $resourcesDirectory = $this->createResourcesDirectory();
        $this->writeDatabase($resourcesDirectory, 'GeoLite2ASN.mmdb', 'GeoLite2-ASN', [
            'autonomous_system_number'       => 64496,
            'autonomous_system_organization' => 'Example Networks',
        ]);

        $client = new AuroraClient($this->createContainer($resourcesDirectory));

        self::assertSame(['name' => 'Example Networks', 'number' => 64496, 'network' => '192.0.2.0/24'], $client->ip2ASN('192.0.2.10'));
        self::assertSame([], $client->ip2ASN('198.51.100.10'));
    }

    private function createResourcesDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/aurora_geolite2_' . uniqid('', true);
        self::assertTrue(mkdir($directory . '/maxmind-geoip2', 0777, true));
        $this->directories[] = $directory;

        return $directory;
    }

    private function createContainer(string $resourcesDirectory): Container
    {
        $container = new Container();
        $container->setParameter('aurora.resources', $resourcesDirectory);

        return $container;
    }

    /**
     * Write an IPv4 MaxMind DB (https://maxmind.github.io/MaxMind-DB/) where only 192.0.2.0/24 has a record: one search tree
     * node per prefix bit, the other branch of every node pointing to "no data"
     *
     * @param array<string, mixed> $record
     */
    private function writeDatabase(string $resourcesDirectory, string $fileName, string $databaseType, array $record): string
    {
        $prefixLength = 24;
        $bits         = '';
        foreach (unpack('C*', (string)inet_pton('192.0.2.0')) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        // Record values: < node count = next node, = node count = no data, > node count = data section offset + node count + 16
        $tree = '';
        for ($node = 0; $node < $prefixLength; $node++) {
            $records                    = [$prefixLength, $prefixLength];
            $records[(int)$bits[$node]] = ($prefixLength - 1 === $node) ? $prefixLength + 16 : $node + 1;
            $tree                      .= substr(pack('N', $records[0]), 1) . substr(pack('N', $records[1]), 1);
        }

        $metadata = [
            'binary_format_major_version' => 2,
            'binary_format_minor_version' => 0,
            'build_epoch'                 => 1700000000,
            'database_type'               => $databaseType,
            'description'                 => ['en' => 'Aurora test database'],
            'ip_version'                  => 4,
            'languages'                   => ['en'],
            'node_count'                  => $prefixLength,
            'record_size'                 => 24,
        ];

        $file = $resourcesDirectory . '/maxmind-geoip2/' . $fileName;
        file_put_contents($file, $tree . str_repeat("\0", 16) . self::encode($record) . "\xAB\xCD\xEFMaxMind.com" . self::encode($metadata));

        return $file;
    }

    private static function encode(mixed $value): string
    {
        if (is_string($value)) {
            return self::controlBytes(2, strlen($value)) . $value;
        }

        if (is_int($value)) {
            $bytes = ltrim(pack('N', $value), "\0");

            return self::controlBytes(6, strlen($bytes)) . $bytes;
        }

        $encoded = self::controlBytes(array_is_list($value) ? 11 : 7, count($value));
        foreach ($value as $key => $item) {
            $encoded .= (array_is_list($value) ? '' : self::encode((string)$key)) . self::encode($item);
        }

        return $encoded;
    }

    private static function controlBytes(int $type, int $size): string
    {
        $extraSize = '';
        if ($size >= 29) {
            $extraSize = chr($size - 29);
            $size      = 29;
        }

        // Types above 7 are "extended": type 0 in the control byte, the type - 7 in the next byte
        return ($type <= 7 ? chr(($type << 5) | $size) : chr($size) . chr($type - 7)) . $extraSize;
    }
}
