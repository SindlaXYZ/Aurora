<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraChronos;

use PHPUnit\Framework\Attributes\DataProvider;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor\AuroraCookiesExtractor;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraChronos/AuroraCookiesExtractorTest.php --no-coverage
 */
class AuroraCookiesExtractorTest extends KernelTestCase
{
    private $auroraCookiesExtractor;

    protected function setUp(): void
    {
        $this->auroraCookiesExtractor = new AuroraCookiesExtractor();
    }

    public function testToStringWithArrayOfArrays(): void
    {
        $cookies = [
            ['name' => 'session_id', 'value' => 'abc123'],
            ['name' => 'user_pref', 'value' => 'dark_mode'],
            ['name' => 'lang', 'value' => 'ro']
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('session_id=abc123; user_pref=dark_mode; lang=ro;', $result);
    }

    public function testToStringWithCookieObjects(): void
    {
        $cookie1 = $this->createMock(Cookie::class);
        $cookie1->method('toArray')->willReturn(['name' => 'test1', 'value' => 'value1']);

        $cookie2 = $this->createMock(Cookie::class);
        $cookie2->method('toArray')->willReturn(['name' => 'test2', 'value' => 'value2']);

        $cookies = [$cookie1, $cookie2];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('test1=value1; test2=value2;', $result);
    }

    public function testToStringWithMixedTypes(): void
    {
        $cookie = $this->createMock(Cookie::class);
        $cookie->method('toArray')->willReturn(['name' => 'from_object', 'value' => 'obj_value']);

        $cookies = [
            ['name' => 'from_array', 'value' => 'array_value'],
            $cookie
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('from_array=array_value; from_object=obj_value;', $result);
    }

    public function testToStringWithEmptyArray(): void
    {
        $cookies = [];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals(';', $result);
    }

    public function testToStringWithSpecialCharacters(): void
    {
        $cookies = [
            ['name' => 'special', 'value' => 'test=value&more'],
            ['name' => 'encoded', 'value' => 'hello world']
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('special=test=value&more; encoded=hello world;', $result);
    }

    public function testToStringWithEmptyValues(): void
    {
        $cookies = [
            ['name' => 'empty_value', 'value' => ''],
            ['name' => 'normal', 'value' => 'test']
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('empty_value=; normal=test;', $result);
    }

    public function testToStringWithSingleCookie(): void
    {
        $cookies = [
            ['name' => 'single', 'value' => 'alone']
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('single=alone;', $result);
    }

    public function testToStringImprovedWithInvalidCookies(): void
    {
        $cookies = [
            ['name' => 'valid', 'value' => 'test'],
            ['invalid' => 'structure'], // Cookie invalid
            ['name' => 'another_valid', 'value' => 'test2']
        ];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('valid=test; another_valid=test2', $result);
    }

    public function testToStringImprovedWithEmptyArray(): void
    {
        $cookies = [];

        $result = $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals('', $result);
    }

    public function testOriginalArrayNotModified(): void
    {
        $originalCookies = [
            ['name' => 'test', 'value' => 'value']
        ];
        $cookies         = $originalCookies;

        $this->auroraCookiesExtractor->toString($cookies);

        $this->assertEquals($originalCookies, [['name' => 'test', 'value' => 'value']]);
    }

    #[DataProvider('cookieDataProvider')]
    public function testToStringWithDataProvider(array $cookies, string $expected): void
    {
        $result = $this->auroraCookiesExtractor->toString($cookies);
        $this->assertEquals($expected, $result);
    }

    public function cookieDataProvider(): array
    {
        return [
            'single_cookie'    => [
                [['name' => 'test', 'value' => 'data']],
                'test=data;'
            ],
            'multiple_cookies' => [
                [
                    ['name' => 'first', 'value' => '1'],
                    ['name' => 'second', 'value' => '2']
                ],
                'first=1; second=2;'
            ],
            'empty_values'     => [
                [['name' => 'empty', 'value' => '']],
                'empty=;'
            ]
        ];
    }
}
