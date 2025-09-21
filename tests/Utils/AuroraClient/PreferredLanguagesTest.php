<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;

final class PreferredLanguagesTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testSkipsLanguagesWithZeroQuality(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US;q=1.0, fr;q=0, es;q=0.0, de;q=0.4';

        $client = new AuroraClient();

        self::assertSame(
            [
                'en-US' => 1.0,
                'de'    => 0.4,
            ],
            $client->preferredLanguages()
        );
    }

    public function testClampsQualityToMaximumOfOne(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es;q=1.5, it;q=2';

        $client = new AuroraClient();

        self::assertSame(
            [
                'es' => 1.0,
                'it' => 1.0,
            ],
            $client->preferredLanguages()
        );
    }
}
