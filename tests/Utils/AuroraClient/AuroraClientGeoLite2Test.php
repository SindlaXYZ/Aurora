<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;
use Symfony\Component\DependencyInjection\Container;

/**
 * Regression test for https://github.com/SindlaXYZ/Aurora/issues/1
 *
 * The GeoLite2 *.mmdb databases are optional (auto-downloaded by the Composer hooks only when the MaxMind
 * env vars are set); when they are missing, the IP lookups must degrade gracefully (null / empty array)
 * instead of throwing and breaking e.g. a Twig template rendering.
 */
class AuroraClientGeoLite2Test extends TestCase
{
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
}
