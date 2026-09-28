<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraStrink;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraStrink\AuroraStrink;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraStrink/StrinkTest.php --no-coverage
 */
class StrinkTest extends TestCase
{
    public function testFixDiacritics(): void
    {
        $Strink = new AuroraStrink();
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
        $Strink = new AuroraStrink();
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
        $Strink = new AuroraStrink();

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
        $Strink = new AuroraStrink();

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
                     'Șîğñ Îñ'                     => ['ȘÎĞÑ_ÎÑ']
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
        $this->assertEquals('Åûţéñţîƒîçåŕé ②', new AuroraStrink()->string('Autentificare 2')->pseudoTranslate());
        $this->assertEquals('Šîĝñ Îñ', new AuroraStrink()->string('Sign In')->pseudoTranslate());
    }

    public function testCompressSpaces()
    {
        $this->assertEquals('Šîĝñ Îñ', new AuroraStrink()->string('Šîĝñ   Îñ')->compressSpaces());
        $this->assertEquals("Šîĝñ \nÎñ", new AuroraStrink()->string("Šîĝñ \nÎñ")->compressSpaces());
        $this->assertEquals('Šîĝñ Îñ', new AuroraStrink()->string("Šîĝñ\x20\x20\x20Îñ")->compressSpaces());
        $this->assertEquals("line1\n\nline2", new AuroraStrink()->string("line1\n\nline2")->compressSpaces());
        $this->assertEquals('Ora de început', new AuroraStrink()->string('Ora de          început')->compressSpaces());
    }

    public function testCompressSlashes(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals('http://example.com/foo/bar', $Strink->string('http://example.com//foo///bar')->compressSlashes());
        $this->assertEquals('/foo/bar', $Strink->string('////foo//bar')->compressSlashes());
        $this->assertEquals('//example.com/path', $Strink->string('//example.com/path')->compressSlashes());
        $this->assertEquals('//example.com/path', $Strink->string('//example.com//path')->compressSlashes());
    }

    public function testCompressDoubleQuotes(): void
    {
        $this->assertEquals("\"Pleașe țest thîs string\"", new AuroraStrink()->string("\"\"Pleașe țest thîs string\"\"")->compressDoubleQuotes());
    }

    public function testCompressQuotes(): void
    {
        $raw      = "\"\"''Test''\"\"";
        $expected = (string)new AuroraStrink()
            ->string($raw)
            ->compressSimpleQuotes()
            ->compressDoubleQuotes()
            ->compressSimpleQuotes()
            ->compressDoubleQuotes();

        $this->assertEquals(
            $expected,
            (string)new AuroraStrink()->string($raw)->compressQuotes()
        );
    }

    public function testSlugify(): void
    {
        $Strink = new AuroraStrink();

        $this->assertEquals(
            'lorem-ipsum',
            (string)$Strink->string('Lorem Ipsum')->slugify()
        );

        $this->assertEquals(
            'șîĝñ-îñ',
            (string)$Strink->string('Șîĝñ Îñ')->slugify(true)
        );
    }

    public function testRemoveNewLines(): void
    {
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("Lorem\nIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("\nLorem\nIsum\n")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("Lorem\n\nIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("\n\nLorem\n\nIsum\n\n")->removeNewLines());

        $this->assertEquals('Lorem Isum', new AuroraStrink()->string("Lorem\nIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new AuroraStrink()->string("\nLorem\nIsum\n")->removeNewLines("\x20"));
        $this->assertEquals('Lorem  Isum', new AuroraStrink()->string("Lorem\n\nIsum")->removeNewLines("\x20"));
        $this->assertEquals('  Lorem  Isum  ', new AuroraStrink()->string("\n\nLorem\n\nIsum\n\n")->removeNewLines("\x20"));

        $this->assertEquals('LoremIsum', new AuroraStrink()->string("Lorem\r\nIsum")->removeNewLines());
        $this->assertEquals('Lorem Isum', new AuroraStrink()->string("Lorem\r\nIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new AuroraStrink()->string("\r\nLorem\r\nIsum\r\n")->removeNewLines("\x20"));

        $this->assertEquals('LoremIsum', new AuroraStrink()->string("Lorem\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("\rLorem\rIsum\r")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("Lorem\r\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new AuroraStrink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines());


        $this->assertEquals('Lorem Isum', new AuroraStrink()->string("Lorem\rIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new AuroraStrink()->string("\rLorem\rIsum\r")->removeNewLines("\x20"));
        $this->assertEquals('Lorem  Isum', new AuroraStrink()->string("Lorem\r\rIsum")->removeNewLines("\x20"));
        $this->assertEquals('  Lorem  Isum  ', new AuroraStrink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines("\x20"));
    }

    public function testUpperLowerAndUcfirst(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals('Ș', (string)$Strink->string('ș')->upper());
        $this->assertEquals('ș', (string)$Strink->string('Ș')->lower());
        $this->assertEquals('Șarpe', (string)$Strink->string('șarpe')->ucfirst());
    }

    public function testRemoveWords(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals(
            'maro',
            (string)$Strink->string('șarpe maro')->removeWords(['șarpe'])
        );

        $this->assertEquals(
            'Lorem ipsum',
            (string)$Strink->string('Lorem ipsum dolor')->removeWords(['dolor'])
        );
    }

    #[DataProvider('dataObfuscateString')]
    public function testObfuscateString(mixed $input, int $margins, string $expected): void
    {
        $Strink = new AuroraStrink();
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
        $Strink = new AuroraStrink();
        $this->assertEquals('Șîĝñ', (string)$Strink->string('Șîĝñ')->limitedString(4));
        $this->assertEquals('...', (string)$Strink->string('Șîĝñ')->limitedString(3));
        $this->assertEquals('Ș...', (string)$Strink->string('Șîĝñț')->limitedString(4));
        $this->assertEquals('..', (string)$Strink->string('Șîĝñț')->limitedString(2));
    }

    ##########################################################################################################################################################################################

    public function testCharacterCasePercentageWithEmptyString(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals(0.0, $Strink->string('')->lowerCharactersPercentage());
        $this->assertEquals(0.0, $Strink->string('')->upperCharactersPercentage());
    }

    public function testCharacterCasePercentage(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals(50.0, $Strink->string('Aa')->lowerCharactersPercentage());
        $this->assertEquals(50.0, $Strink->string('Aa')->upperCharactersPercentage());
    }

    public function testCharacterCasePercentageWithUtf8(): void
    {
        $Strink = new AuroraStrink();
        $this->assertEquals(50.0, $Strink->string('Șș')->lowerCharactersPercentage());
        $this->assertEquals(50.0, $Strink->string('Șș')->upperCharactersPercentage());
    }

    #[DataProvider('dataStrStartsWithAny')]
    public function testStrStartsWithAny(string $haystack, array $needles, bool $expected): void
    {
        $this->assertEquals($expected, new AuroraStrink()->string($haystack)->strStartsWithAny($needles));
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
        $this->assertEquals($expected, new AuroraStrink()->string($haystack)->strEndsWithAny($needles));
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
        $Strink = new AuroraStrink();
        $this->assertSame(['line1', 'line2'], $Strink->string("line1\nline2\n")->linesToArray());
        $this->assertSame(['line1', '', 'line2'], $Strink->string("line1\n\nline2\n")->linesToArray());
    }

    public function testLinesToArrayTrimsEdges(): void
    {
        $Strink = new AuroraStrink();
        $this->assertSame(['line1', 'line2'], $Strink->string("\n\nline1\nline2\n\n")->linesToArray());
    }

    public function testRandomStringZeroLength(): void
    {
        $Strink = new AuroraStrink();
        $this->assertSame('', (string)$Strink->randomString(0));
    }

    public function testRandomStringIncludesVCharacters(): void
    {
        $result = (string)new AuroraStrink()->randomString(2000);
        $this->assertStringContainsString('v', $result);
        $this->assertStringContainsString('V', $result);
    }

    public function testRandomStringSkipsEmptyKeys(): void
    {
        $result = (string)new AuroraStrink()->randomString(5, ['abc', '']);
        $this->assertSame(5, strlen($result));
        $this->assertMatchesRegularExpression('/^[abc]+$/', $result);
    }

    /**
     * mt_rand() was used: its output reveals its seed, the other passwords generated by the same PHP process could be predicted
     */
    public function testRandomStringDoesNotDependOnTheMtRandSeed(): void
    {
        mt_srand(42);
        $first = (string)new AuroraStrink()->randomString(32);
        mt_srand(42);
        $second = (string)new AuroraStrink()->randomString(32);

        $this->assertNotSame($first, $second);
    }

    /**
     * The sets were used in turn ("hG5@eR5@..."): the set of every position was known
     */
    public function testRandomStringContainsEverySetInARandomOrder(): void
    {
        $firstCharacters = '';

        for ($i = 0; $i < 50; $i++) {
            $result = (string)new AuroraStrink()->randomString(8);

            $this->assertSame(8, strlen($result));
            foreach (['/[a-z]/', '/[A-Z]/', '/[0-9]/', '/[!@#$%^&*+=]/'] as $set) {
                $this->assertMatchesRegularExpression($set, $result);
            }

            $firstCharacters .= $result[0];
        }

        $this->assertDoesNotMatchRegularExpression('/^[a-z]+$/', $firstCharacters);
    }

    public function testRandomStringOfMultibyteCharacters(): void
    {
        $result = (string)new AuroraStrink()->randomString(20, ['ăîșț']);

        // The bytes of the set used to be picked: invalid UTF-8
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertSame(20, mb_strlen($result));
        $this->assertMatchesRegularExpression('/^[ăîșț]+$/u', $result);
    }

    /**
     * The sets were split with mb_internal_encoding(): with ISO-8859-1, "ăîșț" was split into 8 bytes (an invalid UTF-8 password)
     */
    public function testRandomStringOfMultibyteCharactersDoesNotDependOnTheInternalEncoding(): void
    {
        $internalEncoding = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');

        try {
            $result = (string)new AuroraStrink()->randomString(20, ['ăîșț']);
        } finally {
            mb_internal_encoding($internalEncoding);
        }

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertSame(20, mb_strlen($result, 'UTF-8'));
        $this->assertMatchesRegularExpression('/^[ăîșț]+$/u', $result);
    }

    public function testRandomStringWithoutAnyUsableSet(): void
    {
        $this->assertSame('', (string)new AuroraStrink('previous')->randomString(8, ['', '']));
    }

    public function testRandomStringShorterThanTheNumberOfSets(): void
    {
        for ($i = 0; $i < 20; $i++) {
            // One character of each of the first sets: a lowercase and an uppercase letter
            $this->assertMatchesRegularExpression('/^([a-z][A-Z]|[A-Z][a-z])$/', (string)new AuroraStrink()->randomString(2));
        }
    }

    public function testConstructorSetsTheString(): void
    {
        $this->assertSame('Lorem ipsum', (string)new AuroraStrink('Lorem ipsum'));
        $this->assertSame('', (string)new AuroraStrink());
    }

    public function testReplaceKeyValue(): void
    {
        $Strink = new AuroraStrink();

        $this->assertSame(
            'Hello Ana from Cluj, Ana!',
            (string)$Strink->string('Hello {name} from {city}, {name}!')->replaceKeyValue(['{name}' => 'Ana', '{city}' => 'Cluj'])
        );
        $this->assertSame('Unchanged {name}', (string)$Strink->string('Unchanged {name}')->replaceKeyValue([]));
    }

    #[DataProvider('dataClassShortName')]
    public function testClassShortName(string $expected, string $className): void
    {
        $this->assertSame($expected, (string)new AuroraStrink()->string($className)->classShortName());
    }

    public static function dataClassShortName(): array
    {
        return [
            'namespaced class'  => ['AuroraStrink', AuroraStrink::class],
            'leading backslash' => ['DateTimeImmutable', '\\DateTimeImmutable'],
            'global class'      => ['DateTimeImmutable', 'DateTimeImmutable'],
        ];
    }

    #[DataProvider('dataTrim')]
    public function testTrim(string $expected, string $method, string $given, ?string $characters): void
    {
        $this->assertSame($expected, (string)new AuroraStrink()->string($given)->{$method}($characters));
    }

    public static function dataTrim(): array
    {
        return [
            'trim whitespaces'       => ['Lorem ipsum', 'trim', " \t Lorem ipsum \n\r\0", null],
            'trim characters'        => ['path/to', 'trim', '//path/to//', '/'],
            'trim a character range' => ['Lorem', 'trim', '0042Lorem9', '0..9'],
            'left trim whitespaces'  => ["Lorem ipsum \n", 'leftTrim', " \t Lorem ipsum \n", null],
            'left trim characters'   => ['120', 'leftTrim', '000120', '0'],
            'right trim whitespaces' => [" \t Lorem ipsum", 'rightTrim', " \t Lorem ipsum \n", null],
            'right trim characters'  => ['path/to', 'rightTrim', 'path/to//', '/'],
        ];
    }

    public function testTrimMethodsAreFluent(): void
    {
        $Strink = new AuroraStrink('--[Lorem]--');

        $this->assertSame('Lorem', (string)$Strink->leftTrim('-')->rightTrim('-')->trim('[]'));
    }

    /**
     * The middle of the string is replaced by the post text: the result has the requested length, and starts and ends like the string
     */
    #[DataProvider('dataLimitedStringCutInTheMiddle')]
    public function testLimitedStringCutInTheMiddle(string $given, int $limit, string $postText, string $cut): void
    {
        $result = (string)new AuroraStrink()->string($given)->limitedString($limit, $postText, $cut);

        $this->assertSame($limit, mb_strlen($result, 'UTF-8'));
        $this->assertSame(1, substr_count($result, $postText));

        [$left, $right] = explode($postText, $result, 2);
        $this->assertNotSame('', $left);
        $this->assertNotSame('', $right);
        $this->assertStringStartsWith($left, $given);
        $this->assertStringEndsWith($right, $given);
    }

    public static function dataLimitedStringCutInTheMiddle(): array
    {
        return [
            'middle'    => ['abcdefghijklmnopqrst', 10, '...', 'middle'],
            'center'    => ['abcdefghijklmnopqrst', 11, '...', 'center'],
            'multibyte' => ['ăîșțăîșțăîșțĂÎȘȚ', 9, '…', 'middle'],
        ];
    }

    public function testLimitedStringCutInTheMiddleKeepsAShortString(): void
    {
        $this->assertSame('abcdef', (string)new AuroraStrink()->string('abcdef')->limitedString(10, '...', 'middle'));
    }

    ##########################################################################################################################################################################################
}
