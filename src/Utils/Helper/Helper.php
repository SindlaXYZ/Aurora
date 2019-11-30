<?php

namespace Sindla\Bundle\BorealisBundle\Utils\Helper;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use GeoIp2\Database\Reader;

/**
 * Debug: php bin/console debug:container borealis.helper
 */
class Helper
{
    private $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }
}