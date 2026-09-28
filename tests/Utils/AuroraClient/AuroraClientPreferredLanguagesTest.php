<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraClient;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraClient\AuroraClient;

final class AuroraClientPreferredLanguagesTest extends TestCase
{
    private ?string $previousAcceptLanguage;

    protected function setUp(): void
    {
        $this->previousAcceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousAcceptLanguage === null) {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        } else {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $this->previousAcceptLanguage;
        }
    }

    #[DataProvider('preferredLanguageProvider')]
    public function testPreferredLanguagesNormalizesAndDeduplicates(string $header, array $expected): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header;

        $client = new AuroraClient();

        self::assertSame($expected, $client->preferredLanguages());
    }

    public static function preferredLanguageProvider(): array
    {
        return [
            'deduplicates differing case' => [
                'en-us,en-US;q=0.5',
                ['en-US' => 1.0],
            ],
            'prefers highest quality irrespective of case' => [
                'en;q=0.5,EN;q=0.7',
                ['en' => 0.7],
            ],
            'normalizes casing and separators' => [
                'fr_fr;q=0.7,EN-gb;q=0.4',
                ['fr-FR' => 0.7, 'en-GB' => 0.4],
            ],
            'keeps languages sorted by quality' => [
                'en_US,fr_FR;q=0.8',
                ['en-US' => 1.0, 'fr-FR' => 0.8],
            ],
            'skips empty entries and entries without a locale' => [
                ' , ;q=0.5,fr',
                ['fr' => 1.0],
            ],
            'ignores the parameters that are not a quality' => [
                'en;level=1;;q=0.3,de;foo',
                ['de' => 1.0, 'en' => 0.3],
            ],
            'ignores a non numeric quality' => [
                'it;q=high',
                ['it' => 1.0],
            ],
            'title cases the script and lower cases the other subtags' => [
                'zh_hant_tw,sr-LATN;q=0.9,es-419;q=0.8,EN-us-POSIX;q=0.7',
                ['zh-Hant-TW' => 1.0, 'sr-Latn' => 0.9, 'es-419' => 0.8, 'en-US-posix' => 0.7],
            ],
            'tolerates an empty subtag' => [
                'EN--us',
                ['en--US' => 1.0],
            ],
        ];
    }

    public function testPreferredLanguagesIsEmptyWithoutAcceptLanguageHeader(): void
    {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

        self::assertSame([], new AuroraClient()->preferredLanguages());
    }
}
