<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Strink;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Sindla\Bundle\AuroraBundle\Utils\Strink\Strink;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Strink/StrinkTest.php --no-coverage
 */
class StrinkTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

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
    }

    public function testSnakeCaseToCamelCase(): void
    {
        $Strink = new Strink();

        // Upper first letter
        foreach ([
                     'ExternalRequestRepository' => ['external_request_repository', 'external_Request_repository', 'External_request_repository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->snakeCaseToCamelCase(true));
            }
        }

        // Lower first letter
        foreach ([
                     'externalRequestRepository' => ['external_request_repository', 'external_Request_repository', 'External_request_repository']
                 ] as $expected => $givens) {
            foreach ($givens as $given) {
                $this->assertEquals($expected, $Strink->string($given)->snakeCaseToCamelCase(false));
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
        $this->assertEquals('Šîĝñ Îñ', new Strink()->string("Šîĝñ \nÎñ")->compressSpaces());
        $this->assertEquals('Šîĝñ Îñ', new Strink()->string("Šîĝñ\x20\x20\x20Îñ")->compressSpaces());
        $this->assertEquals('Ora de început', new Strink()->string('Ora de          început')->compressSpaces());
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

        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\rLorem\rIsum\r")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("Lorem\r\rIsum")->removeNewLines());
        $this->assertEquals('LoremIsum', new Strink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines());


        $this->assertEquals('Lorem Isum', new Strink()->string("Lorem\rIsum")->removeNewLines("\x20"));
        $this->assertEquals(' Lorem Isum ', new Strink()->string("\rLorem\rIsum\r")->removeNewLines("\x20"));
        $this->assertEquals('Lorem  Isum', new Strink()->string("Lorem\r\rIsum")->removeNewLines("\x20"));
        $this->assertEquals('  Lorem  Isum  ', new Strink()->string("\r\rLorem\r\rIsum\r\r")->removeNewLines("\x20"));
    }

    ##########################################################################################################################################################################################

    public function testCharacterCasePercentageWithEmptyString(): void
    {
        $Strink = new Strink();
        $this->assertEquals(0.0, $Strink->string('')->lowerCharactersPercentage());
        $this->assertEquals(0.0, $Strink->string('')->upperCharactersPercentage());
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
        ];
    }

    ##########################################################################################################################################################################################
}
