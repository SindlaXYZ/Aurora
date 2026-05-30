<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCloudflareR2;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2\AuroraCloudflareR2;

class CloudflareR2Test extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = [
            'CLOUDFLARE_R2_API_ENDPOINT'          => $_ENV['CLOUDFLARE_R2_API_ENDPOINT'] ?? null,
            'CLOUDFLARE_R2_API_ACCESS_KEY_ID'     => $_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID'] ?? null,
            'CLOUDFLARE_R2_API_SECRET_ACCESS_KEY' => $_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function testGetEndpointAndBucketReturnsSplitValues(): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com/my-bucket');

        $cloudflare = new AuroraCloudflareR2();

        self::assertSame('https://example.r2.cloudflarestorage.com', $cloudflare->getEndpoint());
        self::assertSame('my-bucket', $cloudflare->getBucket());
    }

    public function testTrailingSlashIsIgnoredWhenSplittingEndpoint(): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com/my-bucket/');

        $cloudflare = new AuroraCloudflareR2();

        self::assertSame('https://example.r2.cloudflarestorage.com', $cloudflare->getEndpoint());
        self::assertSame('my-bucket', $cloudflare->getBucket());
    }

    public function testExceptionIsThrownWhenBucketSegmentIsMissing(): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com');

        $cloudflare = new AuroraCloudflareR2();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bucket name');

        $cloudflare->getEndpoint();
    }

    private function seedEnv(string $endpoint): void
    {
        $_ENV['CLOUDFLARE_R2_API_ENDPOINT']          = $endpoint;
        $_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID']     = 'access-key';
        $_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'] = 'secret-key';
    }
}
