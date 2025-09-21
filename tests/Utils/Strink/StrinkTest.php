<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Strink/StrinkTest.php --no-coverage
 */
class StrinkTest extends TestCase
{

    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testFixDiacritics(): void
    {
        $Strink = new Strink();
        foreach ([
                     'București'           => ['Bucureºti', 'Bucureşti'],
                     'Dumbrăvii'           => ['Dumbrãvii'],
                     'P-ța Rhedey Claudia' => ['P-þa Rhedey Claudia']
                 ] as $expected => $givents) {
            foreach ($givents as $given) {
                $this->assertEquals($expected, $Strink->string($given)->fixDiacritics('ro'));
            }
        }
    }

    public function testCamelCaseToSnakeCase(): void
    {
        $Strink = new Strink();
        foreach ([
                     'external_request_repository' => ['ExternalRequestRepository', 'externalRequestRepository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->camelCaseToSnakeCase());
            }
        }

        foreach ([
                     'xml_http_request' => ['XMLHttpRequest', 'XmlHTTPRequest'],
                     'api_response'     => ['APIResponse'],
                     'json_rpc'         => ['JsonRPC']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->camelCaseToSnakeCase());
            }
        }

        // UTF-8 support
        $this->assertEquals('denumire_șarpe', $Strink->string('DenumireȘarpe')->camelCaseToSnakeCase());
    }

    public function testSnakeCaseToCamelCase(): void
    {
        $Strink = new Strink();

        // Upper first letter
        foreach ([
                     'ExternalRequestRepository' => ['external_request_repository', 'external_Request_repository', 'External_request_repository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->snakeCaseToCamelCase(upperCaseFirstLetter: true));
            }
        }

        // Lower first letter
        foreach ([
                     'externalRequestRepository' => ['external_request_repository', 'external_Request_repository', 'External_request_repository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->snakeCaseToCamelCase(upperCaseFirstLetter: false));
            }
        }

        // UTF-8 support
        $this->assertEquals('ȘarpeMagic', $Strink->string('șarpe_magic')->snakeCaseToCamelCase(upperCaseFirstLetter: true));
        $this->assertEquals('șarpeMagic', $Strink->string('șarpe_magic')->snakeCaseToCamelCase(upperCaseFirstLetter: false));
    }

    public function testSnakeCaseToHumanCase(): void
    {
        $Strink = new Strink();

        foreach ([
                     'external request repository' => [
                         'external_request_repository',
                         'external__request__repository',
                         '__external_request__repository__'
                     ]
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->snakeCaseToHumanCase());
            }
        }

        foreach ([
                     'External request repository' => ['external_request_repository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals(
                    $expected,
                    $Strink->string($given)->snakeCaseToHumanCase(upperCaseFirstLetter: true)
                );
            }
        }

        foreach ([
                     'External Request Repository' => ['external_request_repository'],
                     'Șîğñ Îñ'                    => ['ȘÎĞÑ_ÎÑ']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals(
                    $expected,
                    $Strink->string($given)->snakeCaseToHumanCase(upperCaseAllLetters: true)
                );
            }
        }
    }

    public function testPseudoTranslate()
    {
        $this->assertEquals('Åûţéñţîƒîçåŕé ②', new Strink()->string('Autentificare 2')->pseudoTranslate());
        $this->assertEquals('Šîĝñ Îñ', new Strink()->string('Sign In')->pseudoTranslate());
    }

    public function testCompressSpaces()
    {
        $this->assertEquals('Šîĝñ Îñ', new Strink()->string('Šîĝñ   Îñ')->compressSpaces());
        $this->assertEquals("Šîĝñ \nÎñ", new Strink()->string("Šîĝñ \nÎñ")->compressSpaces());
        $this->assertEquals('Šîĝñ Îñ', new Strink()->string("Šîĝñ\x20\x20\x20Îñ")->compressSpaces());
        $this->assertEquals("line1\n\nline2", new Strink()->string("line1\n\nline2")->compressSpaces());
        $this->assertEquals('Ora de început', new Strink()->string('Ora de          început')->compressSpaces());
    }

    public function testCompressSlashes(): void
    {
        $Strink = new Strink();
        $this->assertEquals('http://example.com/foo/bar', $Strink->string('http://example.com//foo///bar')->compressSlashes());
        $this->assertEquals('/foo/bar', $Strink->string('////foo//bar')->compressSlashes());
        $this->assertEquals('//example.com/path', $Strink->string('//example.com/path')->compressSlashes());
        $this->assertEquals('//example.com/path', $Strink->string('//example.com//path')->compressSlashes());
    }

    public function testCompressDoubleQuotes(): void
    {
        $this->assertEquals("\"Pleașe țest thîs string\"", new Strink()->string("\"\"Pleașe țest thîs string\"\"")->compressDoubleQuotes());
    }

    public function testCompressQuotes(): void
    {
        $raw = "\"\"''Test''\"\"";
        $expected = (string) (new Strink())
            ->string($raw)
            ->compressSimpleQuotes()
            ->compressDoubleQuotes()
            ->compressSimpleQuotes()
            ->compressDoubleQuotes();

        $this->assertEquals(
            $expected,
            (string) (new Strink())->string($raw)->compressQuotes()
        );
    }

    public function testSlugify(): void
    {
        $Strink = new Strink();

        $this->assertEquals(
            'lorem-ipsum',
            (string) $Strink->string('Lorem Ipsum')->slugify()
        );

        $this->assertEquals(
            'șîĝñ-îñ',
            (string) $Strink->string('Șîĝñ Îñ')->slugify(true)
        );
    }

    public function testRemoveNewLines(): void
    {
        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\nIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\nLorem\nIsum\n")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\n\nIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\n\nLorem\n\nIsum\n\n")->removeNewLines());

        $this->assertEquals('Lorem Isum', new Strink()->string("Lorem\nIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new Strink()->string("\nLorem\nIsum\n")->removeNewLines("\x20"));
        $this->assertEquals('Lorem  Isum', new Strink()->string("Lorem\n\nIsum")->removeNewLines("\x20"));
        $this->assertEquals('  Lorem  Isum  ', new Strink()->string("\n\nLorem\n\nIsum\n\n")->removeNewLines("\x20"));

        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\r\nIsum")->removeNewLines());
        $this->assertEquals('Lorem Isum', new Strink()->string("Lorem\r\nIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new Strink()->string("\r\nLorem\r\nIsum\r\n")->removeNewLines("\x20"));

        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\rLorem\rIsum\r")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\r\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines());


        $this->assertEquals('Lorem Isum', new Strink()->string("Lorem\rIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new Strink()->string("\rLorem\rIsum\r")->removeNewLines("\x20"));
        $this->assertEquals('Lorem  Isum', new Strink()->string("Lorem\r\rIsum")->removeNewLines("\x20"));
        $this->assertEquals('  Lorem  Isum  ', new Strink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines("\x20"));
    }

    public function testUpperLowerAndUcfirst(): void
    {
        $Strink = new Strink();
        $this->assertEquals('Ș', (string) $Strink->string('ș')->upper());
        $this->assertEquals('ș', (string) $Strink->string('Ș')->lower());
        $this->assertEquals('Șarpe', (string) $Strink->string('șarpe')->ucfirst());
    }

    public function testRemoveWords(): void
    {
        $Strink = new Strink();
        $this->assertEquals(
            'maro',
            (string) $Strink->string('șarpe maro')->removeWords(['șarpe'])
        );

        $this->assertEquals(
            'Lorem ipsum',
            (string) $Strink->string('Lorem ipsum dolor')->removeWords(['dolor'])
        );
    }

    #[DataProvider('dataObfuscateString')]
    public function testObfuscateString(mixed $input, int $margins, string $expected): void
    {
        $Strink = new Strink();
        $this->assertEquals($expected, $Strink->obfuscateString($input, $margins));
    }

    public static function dataObfuscateString(): array
    {
        return [
            ['mysecretstring', 2, 'my**********ng'],
            ['mysecretstring', 4, 'myse******ring'],
            ['short', 10, 'short'],
            ['șarpe', 2, 'șa*pe'],
            [12345, 2, '12*45'],
            ['secret', 0, '******'],
        ];
    }

    public function testLimitedString(): void
    {
        $Strink = new Strink();
        $this->assertEquals('Șîĝñ', (string) $Strink->string('Șîĝñ')->limitedString(4));
        $this->assertEquals('...', (string) $Strink->string('Șîĝñ')->limitedString(3));
        $this->assertEquals('Ș...', (string) $Strink->string('Șîĝñț')->limitedString(4));
        $this->assertEquals('..', (string) $Strink->string('Șîĝñț')->limitedString(2));
    }

    ##########################################################################################################################################################################################

    public function testCharacterCasePercentageWithEmptyString(): void
    {
        $Strink = new Strink();
        $this->assertEquals(0.0, $Strink->string('')->lowerCharactersPercentage());
        $this->assertEquals(0.0, $Strink->string('')->upperCharactersPercentage());
    }

    public function testCharacterCasePercentage(): void
    {
        $Strink = new Strink();
        $this->assertEquals(50.0, $Strink->string('Aa')->lowerCharactersPercentage());
        $this->assertEquals(50.0, $Strink->string('Aa')->upperCharactersPercentage());
    }

    public function testCharacterCasePercentageWithUtf8(): void
    {
        $Strink = new Strink();
        $this->assertEquals(50.0, $Strink->string('Șș')->lowerCharactersPercentage());
        $this->assertEquals(50.0, $Strink->string('Șș')->upperCharactersPercentage());
    }

    #[DataProvider('dataStrStartsWithAny')]
    public function testStrStartsWithAny(string $haystack, array $needles, bool $expected): void
    {
        $this->assertEquals($expected, new Strink()->string($haystack)->strStartsWithAny($needles));
    }

    public static function dataStrStartsWithAny(): array
    {
        return [
            ['lorem ipsum', ['lorem'], true],
            ['lorem ipsum', ['lorem', 'dolor'], true],
            ['lorem ipsum', ['lorem', 'ipsum', 'dolor'], true],
            ['lorem ipsum', ['ipsum', 'dolor'], false],
            ['lorem ipsum', ['ipsum lorem'], false],
            ['lorem ipsum', ['lorem ipsum dolor'], false],
            ['lorem ipsum', [''], false],
        ];
    }

    ##########################################################################################################################################################################################

    #[DataProvider('dataStrEndsWithAny')]
    public function testStrEndsWithAny(string $haystack, array $needles, bool $expected): void
    {
        $this->assertEquals($expected, new Strink()->string($haystack)->strEndsWithAny($needles));
    }

    public static function dataStrEndsWithAny(): array
    {
        return [
            ['lorem ipsum', ['ipsum'], true],
            ['lorem ipsum', ['ipsum', 'dolor'], true],
            ['lorem ipsum', ['lorem', 'ipsum', 'dolor'], true],
            ['lorem ipsum', ['lorem', 'dolor'], false],
            ['lorem ipsum', ['lorem ipsum'], true],
            ['lorem ipsum', ['lorem ipsum dolor'], false],
            ['lorem ipsum', [''], false],
        ];
    }

    public function testLinesToArray(): void
    {
        $Strink = new Strink();
        $this->assertSame(['line1', 'line2'], $Strink->string("line1\nline2\n")->linesToArray());
        $this->assertSame(['line1', '', 'line2'], $Strink->string("line1\n\nline2\n")->linesToArray());
    }

    public function testLinesToArrayTrimsEdges(): void
    {
        $Strink = new Strink();
        $this->assertSame(['line1', 'line2'], $Strink->string("\n\nline1\nline2\n\n")->linesToArray());
    }

    public function testRandomStringZeroLength(): void
    {
        $Strink = new Strink();
        $this->assertSame('', (string) $Strink->randomString(0));
    }

    public function testRandomStringIncludesVCharacters(): void
    {
        mt_srand(0);
        $result = (string) (new Strink())->randomString(1000);
        $this->assertStringContainsString('v', $result);
        $this->assertStringContainsString('V', $result);
    }

    public function testRandomStringSkipsEmptyKeys(): void
    {
        mt_srand(1);
        $result = (string) (new Strink())->randomString(5, ['abc', '']);
        $this->assertSame(5, strlen($result));
        $this->assertMatchesRegularExpression('/^[abc]+$/', $result);
    }

    ##########################################################################################################################################################################################
}
