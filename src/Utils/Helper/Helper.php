<?php

namespace Sindla\Bundle\auroraBundle\Utils\Helper;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use GeoIp2\Database\Reader;

/**
 * Debug: php bin/console debug:container aurora.helper
 */
class Helper
{
    private $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }
}