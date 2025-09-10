<?php
declare(strict_types=1);

namespace Symfony\Contracts\HttpClient {
    interface ResponseInterface {
        public function getInfo(?string $type = null): mixed;
    }
}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCookiesExtractor {
    use DateTimeImmutable;
    use DateTimeZone;
    use PHPUnit\Framework\TestCase;
    use Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor\AuroraCookiesExtractor;
    use Symfony\Contracts\HttpClient\ResponseInterface;

    class AuroraCookiesExtractorExtractTest extends TestCase
    {
        public function testMaxAgeSetsRelativeExpiry(): void
        {
            $response = new class implements ResponseInterface {
                public function getInfo(?string $type = null): mixed
                {
                    return [
                        'response_headers' => ['set-cookie: session=abc; Max-Age=60']
                    ];
                }
            };

            $extractor = new AuroraCookiesExtractor();
            $cookies = $extractor->extractFromSymfonyResponseInterface($response);

            $this->assertCount(1, $cookies);
            $expires = $cookies[0]->getExpires();
            $this->assertInstanceOf(DateTimeImmutable::class, $expires);
            $diff = $expires->getTimestamp() - (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp();
            $this->assertGreaterThanOrEqual(59, $diff);
            $this->assertLessThanOrEqual(60, $diff);
        }

        public function testHeaderCaseInsensitive(): void
        {
            $response = new class implements ResponseInterface {
                public function getInfo(?string $type = null): mixed
                {
                    return [
                        'response_headers' => ['Set-Cookie: test=1']
                    ];
                }
            };

            $extractor = new AuroraCookiesExtractor();
            $cookies = $extractor->extractFromSymfonyResponseInterface($response);

            $this->assertCount(1, $cookies);
            $this->assertSame('test', $cookies[0]->getName());
            $this->assertSame('1', $cookies[0]->getValue());
        }
    }
}
