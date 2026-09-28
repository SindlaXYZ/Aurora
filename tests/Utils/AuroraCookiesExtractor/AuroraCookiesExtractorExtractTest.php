<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCookiesExtractor;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor\AuroraCookiesExtractor;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AuroraCookiesExtractorExtractTest extends TestCase
{
    public function testMaxAgeSetsRelativeExpiry(): void
    {
        $response = new class implements ResponseInterface {
            public function getStatusCode(): int
            {
            }

            public function getHeaders(bool $throw = true): array
            {
            }

            public function getContent(bool $throw = true): string
            {
            }

            public function toArray(bool $throw = true): array
            {
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                return [
                    'response_headers' => ['set-cookie: session=abc; Max-Age=60']
                ];
            }
        };

        $extractor = new AuroraCookiesExtractor();
        $cookies   = $extractor->extractFromSymfonyResponseInterface($response);

        $this->assertCount(1, $cookies);
        $expires = $cookies[0]->getExpires();
        $this->assertInstanceOf(\DateTimeImmutable::class, $expires);
        $diff = $expires->getTimestamp() - new \DateTimeImmutable('now', new \DateTimeZone('UTC'))->getTimestamp();
        $this->assertGreaterThanOrEqual(59, $diff);
        $this->assertLessThanOrEqual(60, $diff);
    }

    public function testHeaderCaseInsensitive(): void
    {
        $response = new class implements ResponseInterface {
            public function getStatusCode(): int
            {
            }

            public function getHeaders(bool $throw = true): array
            {
            }

            public function getContent(bool $throw = true): string
            {
            }

            public function toArray(bool $throw = true): array
            {
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                return [
                    'response_headers' => ['Set-Cookie: test=1']
                ];
            }
        };

        $extractor = new AuroraCookiesExtractor();
        $cookies   = $extractor->extractFromSymfonyResponseInterface($response);

        $this->assertCount(1, $cookies);
        $this->assertSame('test', $cookies[0]->getName());
        $this->assertSame('1', $cookies[0]->getValue());
    }

    public function testInvalidExpiresStringPreservesAttribute(): void
    {
        $response = new class implements ResponseInterface {
            public function getStatusCode(): int
            {
            }

            public function getHeaders(bool $throw = true): array
            {
            }

            public function getContent(bool $throw = true): string
            {
            }

            public function toArray(bool $throw = true): array
            {
            }

            public function cancel(): void
            {
            }

            public function getInfo(?string $type = null): mixed
            {
                return [
                    'response_headers' => ['Set-Cookie: token=value; Expires=Not a valid date; Secure']
                ];
            }
        };

        $extractor = new AuroraCookiesExtractor();
        $cookies   = $extractor->extractFromSymfonyResponseInterface($response);

        $this->assertCount(1, $cookies);
        $cookie = $cookies[0];

        $this->assertNull($cookie->getExpires());
        $this->assertSame(
            ['Expires' => 'Not a valid date'],
            $cookie->getAttributes()
        );
    }

    public function testNoCookieWithoutTheListOfResponseHeaders(): void
    {
        $extractor = new AuroraCookiesExtractor();

        $this->assertSame([], $extractor->extractFromSymfonyResponseInterface($this->createResponse([])));
        $this->assertSame([], $extractor->extractFromSymfonyResponseInterface($this->createResponse(['response_headers' => 'Set-Cookie: a=b'])));
    }

    public function testOnlyTheSetCookieHeadersWithANameAndAValueAreExtracted(): void
    {
        $cookies = new AuroraCookiesExtractor()->extractFromSymfonyResponseInterface($this->createResponse([
            'response_headers' => [
                'HTTP/1.1 200 OK',
                'Content-Type: text/html',
                'X-Set-Cookie: ignored=1',
                'Set-Cookie: ',
                'Set-Cookie: flag-without-value; Path=/',
                'Set-Cookie: token=abc=def',
            ],
        ]));

        $this->assertCount(1, $cookies);
        $this->assertSame('token', $cookies[0]->getName());
        // Only the first "=" separates the name from the value
        $this->assertSame('abc=def', $cookies[0]->getValue());
        $this->assertNull($cookies[0]->getExpires());
        $this->assertNull($cookies[0]->getAttributes());
    }

    public function testTheKnownAttributesAreMappedToTheCookie(): void
    {
        $cookies = new AuroraCookiesExtractor()->extractFromSymfonyResponseInterface($this->createResponse([
            'response_headers' => [
                'Set-Cookie: id=a3fWa; Expires=Wed, 21 Oct 2037 07:28:00 GMT; Path=/docs; Domain=example.com; SameSite=Lax; Secure; HttpOnly;',
                'Set-Cookie: lang=ro; path=/; DOMAIN=example.com; samesite=Strict; secure; httponly; Partitioned',
            ],
        ]));

        $this->assertCount(2, $cookies);

        [$id, $lang] = $cookies;
        $this->assertSame('id', $id->getName());
        $this->assertSame('a3fWa', $id->getValue());
        $this->assertSame('2037-10-21 07:28:00 +00:00', $id->getExpires()?->format('Y-m-d H:i:s P'));
        $this->assertSame('/docs', $id->getPath());
        $this->assertSame('example.com', $id->getDomain());
        $this->assertSame('Lax', $id->getSameSite());
        $this->assertTrue($id->getSecure());
        $this->assertTrue($id->getHttpOnly());
        // Every attribute is handled: none is left over
        $this->assertNull($id->getAttributes());

        // The attribute names are case-insensitive, the unknown ones are kept as attributes
        $this->assertSame('/', $lang->getPath());
        $this->assertSame('example.com', $lang->getDomain());
        $this->assertSame('Strict', $lang->getSameSite());
        $this->assertTrue($lang->getSecure());
        $this->assertTrue($lang->getHttpOnly());
        $this->assertSame(['Partitioned' => true], $lang->getAttributes());
    }

    public function testAPhpSessionCookieWithoutExpiryGetsTheDefaultSessionLifetime(): void
    {
        $before  = time();
        $cookies = new AuroraCookiesExtractor()->extractFromSymfonyResponseInterface($this->createResponse([
            'response_headers' => ['Set-Cookie: PHPSESSID=abc; path=/'],
        ]));
        $after   = time();

        $this->assertCount(1, $cookies);
        $expires = $cookies[0]->getExpires();
        $this->assertInstanceOf(\DateTimeImmutable::class, $expires);
        // session.gc_maxlifetime default: 1440 seconds
        $this->assertGreaterThanOrEqual($before + 1440, $expires->getTimestamp());
        $this->assertLessThanOrEqual($after + 1440, $expires->getTimestamp());
    }

    private function createResponse(mixed $info): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getInfo')->willReturn($info);

        return $response;
    }
}
