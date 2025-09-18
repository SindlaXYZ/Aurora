<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Params;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Yaml\Yaml;

@trigger_error('Aurora/Params class is deprecated, use `config/packages/aurora.yaml` parameters: aurora.* instead.', E_USER_DEPRECATED);

class Params
{
    private Container $container;

    /**
     * @var array<string, mixed>
     */
    private array $params;

    public function __construct(Container $Container)
    {
        $this->container = $Container;

        $configPath = $this->container->getParameter('kernel.project_dir') . '/config/packages/aurora.yaml';
        $yaml       = file_get_contents($configPath);

        if ($yaml === false) {
            throw new \RuntimeException(sprintf('Unable to read configuration file "%s".', $configPath));
        }

        /** @var mixed $parsedRaw */
        $parsedRaw = Yaml::parse($yaml);

        if (!is_array($parsedRaw) || !isset($parsedRaw['aurora']) || !is_array($parsedRaw['aurora'])) {
            throw new \UnexpectedValueException(sprintf('Configuration file "%s" must define an "aurora" array.', $configPath));
        }

        /** @var array<string, mixed> $parsed */
        $parsed = $parsedRaw;

        $this->params = $parsed['aurora'];

        if (isset($this->params['tmp'])) {
            $this->params['tmp'] = $this->container->getParameter('kernel.project_dir') . '/' . $this->params['tmp'];
        }

        if (isset($this->params['resources'])) {
            $this->params['resources'] = $this->container->getParameter('kernel.project_dir') . '/' . $this->params['resources'];
        }

        if (isset($this->params['pwa']['icons'])) {
            $this->params['pwa']['icons'] = $this->container->getParameter('kernel.project_dir') . '/' . $this->params['pwa']['icons'];
        }

    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->params;
    }
}