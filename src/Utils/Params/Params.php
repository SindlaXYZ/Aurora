<?php

namespace Sindla\Bundle\BorealisBundle\Utils\Params;

use Symfony\Component\Yaml\Yaml;

@trigger_error('Borealis/Params class is deprecated, use `config/packages/borealis.yaml` parameters: borealis.* instead.', E_USER_DEPRECATED);

class Params
{
    private $container;
    private $params;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
        $this->params    = Yaml::parse(file_get_contents($this->container->getParameter('kernel.project_dir') . '/config/packages/borealis.yaml'))['borealis'];

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

    public function getAll()
    {
        return $this->params;
    }
}