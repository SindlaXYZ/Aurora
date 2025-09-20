<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;

final class AuroraClientPreferredLanguagesStandaloneTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testPreferredLanguagesHandlesWhitespaceAroundQuality(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US; q=0.8, fr; q=0.6';
        $client                          = new AuroraClient();

        $this->assertSame(
            [
                'en-US' => 0.8,
                'fr'    => 0.6,
            ],
            $client->preferredLanguages()
        );
    }

    public function testPreferredLanguagesIsCaseInsensitiveForQualityKey(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES, de; Q=0.4';
        $client                          = new AuroraClient();

        $this->assertSame(
            [
                'es-ES' => 1.0,
                'de'    => 0.4,
            ],
            $client->preferredLanguages()
        );
    }
}
