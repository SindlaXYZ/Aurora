<?php

namespace Sindla\Bundle\AuroraBundle\Service\Helper;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use GeoIp2\Database\Reader;

/**
 * Debug: php bin/console debug:container aurora.helper
 */
class Helper
{
    private $Container;

    public function __construct(Container $Container)
    {
        $this->Container = $Container;
    }
}