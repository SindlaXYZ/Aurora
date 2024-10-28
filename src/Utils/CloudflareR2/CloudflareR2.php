<?php

namespace Sindla\Bundle\AuroraBundle\Utils\CloudflareR2;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

readonly class CloudflareR2
{
    /**
     * @throws \Exception
     */
    public function getEndpoint(): string
    {
        $this->checkCredentials();
        preg_match('#^(.*)/([^/]+)$#', $_ENV['CLOUDFLARE_R2_API_ENDPOINT'], $matches);
        return $matches[1];
    }

    /**
     * @throws \Exception
     */
    public function getBucket(): string
    {
        $this->checkCredentials();
        preg_match('#^(.*)/([^/]+)$#', $_ENV['CLOUDFLARE_R2_API_ENDPOINT'], $matches);
        return $matches[2];
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
        if (!isset($_ENV['CLOUDFLARE_R2_API_ENDPOINT'])) {
            throw new \Exception('CLOUDFLARE_R2_API_ENDPOINT is not set');
        } else if (!isset($_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID'])) {
            throw new \Exception('CLOUDFLARE_R2_API_ACCESS_KEY_ID is not set');
        } else if (!isset($_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'])) {
            throw new \Exception('CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is not set');
        }

        if (isset($_ENV['CLOUDFLARE_R2_API_ENDPOINT']) && !str_starts_with($_ENV['CLOUDFLARE_R2_API_ENDPOINT'], 'https://')) {
            throw new \Exception('CLOUDFLARE_R2_API_ENDPOINT must start with https://');
        } else if (isset($_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID']) && empty($_ENV['CLOUDFLARE_R2_API_ACCESS_KEY_ID'])) {
            throw new \Exception('CLOUDFLARE_R2_API_ACCESS_KEY_ID is empty');
        } else if (isset($_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY']) && empty($_ENV['CLOUDFLARE_R2_API_SECRET_ACCESS_KEY'])) {
            throw new \Exception('CLOUDFLARE_R2_API_SECRET_ACCESS_KEY is empty');
        }
    }
}
