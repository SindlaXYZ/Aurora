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
        $diff = $expires->getTimestamp() - (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp();
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
}
