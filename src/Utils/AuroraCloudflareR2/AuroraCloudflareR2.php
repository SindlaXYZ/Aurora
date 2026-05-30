<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

readonly class AuroraCloudflareR2
{
    /**
     * @throws \RuntimeException
     */
    public function getEndpoint(): string
    {
        $this->checkCredentials();
        [$endpoint] = $this->splitEndpointAndBucket();

        return $endpoint;
    }

    /**
     * @throws \RuntimeException
     */
    public function getBucket(): string
    {
        $this->checkCredentials();
        [, $bucket] = $this->splitEndpointAndBucket();

        return $bucket;
    }

    /**
     * @throws \Exception
     */
    public function createClient(): S3Client
    {
        $this->checkCredentials();
        $credentials = new Credentials($_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID'], $_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY']);
        $options     = [
            'region'      => 'auto',
            'endpoint'    => $this->getEndpoint(),
            'version'     => 'latest',
            'credentials' => $credentials
        ];

        return new S3Client($options);
    }

    private function checkCredentials(): void
    {
        $endpoint  = $_ENV['CLOUDFLARE_R2_API_ENDPOINT'] ?? null;
        $accessKey = $_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID'] ?? null;
        $secretKey = $_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'] ?? null;

        if ($endpoint === null) {
            throw new \Exception('CLOUDFLARE_R2_API_ENDPOINT is not set');
        }

        if ($accessKey === null) {
            throw new \Exception('CLOUDFLARE_R2_API_ACCESS_KEY_ID is not set');
        }

        if ($secretKey === null) {
            throw new \Exception('CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is not set');
        }

        if (!str_starts_with($endpoint, 'https://')) {
            throw new \Exception('CLOUDFLARE_R2_API_ENDPOINT must start with https://');
        }

        if ($accessKey === '') {
            throw new \Exception('CLOUDFLARE_R2_API_ACCESS_KEY_ID is empty');
        }

        if ($secretKey === '') {
            throw new \Exception('CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is empty');
        }
    }

    /**
     * @return array{string, string}
     *
     * @throws \RuntimeException
     */
    private function splitEndpointAndBucket(): array
    {
        $endpoint = rtrim($_ENV['CLOUDFLARE_R2_API_ENDPOINT'], '/');
        $parsedEndpoint = parse_url($endpoint);

        if ($parsedEndpoint === false || !isset($parsedEndpoint['scheme'], $parsedEndpoint['host'])) {
            throw new \RuntimeException('CLOUDFLARE_R2_API_ENDPOINT must be a valid URL.');
        }

        $path = $parsedEndpoint['path'] ?? '';
        $bucket = ltrim($path, '/');

        if ($bucket === '') {
            throw new \RuntimeException('CLOUDFLARE_R2_API_ENDPOINT must include a non-empty bucket name.');
        }

        $baseEndpoint = $parsedEndpoint['scheme'] . '://' . $parsedEndpoint['host'];

        if (isset($parsedEndpoint['port'])) {
            $baseEndpoint .= ':' . $parsedEndpoint['port'];
        }

        return [$baseEndpoint, $bucket];
    }
}
