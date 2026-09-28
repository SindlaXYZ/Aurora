<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCloudflareR2;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testCreateClientTargetsTheEndpointWithoutTheBucket(): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com/my-bucket');

        // Building the client does not send any request
        $client = new AuroraCloudflareR2()->createClient();

        self::assertInstanceOf(S3Client::class, $client);
        self::assertSame('https://example.r2.cloudflarestorage.com', (string)$client->getEndpoint());
        self::assertSame('auto', $client->getRegion());

        $credentials = $client->getCredentials()->wait();
        self::assertInstanceOf(Credentials::class, $credentials);
        self::assertSame('access-key', $credentials->getAccessKeyId());
        self::assertSame('secret-key', $credentials->getSecretKey());
    }

    public function testCreateClientRequiresTheCredentials(): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com/my-bucket');
        unset($_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is not set');

        new AuroraCloudflareR2()->createClient();
    }

    /**
     * @param array<string, string|null> $env
     */
    #[DataProvider('dataInvalidCredentials')]
    public function testInvalidCredentialsAreRejected(array $env, string $expectedMessage): void
    {
        $this->seedEnv('https://example.r2.cloudflarestorage.com/my-bucket');

        foreach ($env as $key => $value) {
            if (null === $value) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        new AuroraCloudflareR2()->getBucket();
    }

    public static function dataInvalidCredentials(): iterable
    {
        yield 'missing endpoint' => [['CLOUDFLARE_R2_API_ENDPOINT' => null], 'CLOUDFLARE_R2_API_ENDPOINT is not set'];
        yield 'missing access key' => [['CLOUDFLARE_R2_API_ACCESS_KEY_ID' => null], 'CLOUDFLARE_R2_API_ACCESS_KEY_ID is not set'];
        yield 'missing secret key' => [['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY' => null], 'CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is not set'];
        yield 'plain HTTP endpoint' => [
            ['CLOUDFLARE_R2_API_ENDPOINT' => 'http://example.r2.cloudflarestorage.com/my-bucket'],
            'CLOUDFLARE_R2_API_ENDPOINT must start with https://',
        ];
        yield 'empty access key' => [['CLOUDFLARE_R2_API_ACCESS_KEY_ID' => ''], 'CLOUDFLARE_R2_API_ACCESS_KEY_ID is empty'];
        yield 'empty secret key' => [['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY' => ''], 'CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is empty'];
    }

    #[DataProvider('dataEndpointsWithoutHost')]
    public function testEndpointWithoutHostIsRejected(string $endpoint): void
    {
        $this->seedEnv($endpoint);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CLOUDFLARE_R2_API_ENDPOINT must be a valid URL.');

        new AuroraCloudflareR2()->getEndpoint();
    }

    public static function dataEndpointsWithoutHost(): iterable
    {
        yield 'scheme only' => ['https://'];
        yield 'empty host' => ['https:///my-bucket'];
    }

    public function testEndpointKeepsThePort(): void
    {
        $this->seedEnv('https://r2.example.com:8443/my-bucket');

        $cloudflare = new AuroraCloudflareR2();

        self::assertSame('https://r2.example.com:8443', $cloudflare->getEndpoint());
        self::assertSame('my-bucket', $cloudflare->getBucket());
    }

    private function seedEnv(string $endpoint): void
    {
        $_ENV['CLOUDFLARE_R2_API_ENDPOINT']          = $endpoint;
        $_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID']     = 'access-key';
        $_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'] = 'secret-key';
    }
}
