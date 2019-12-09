<?php

namespace Sindla\Bundle\AuroraBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

// Aurora
use Sindla\Bundle\AuroraBundle\DependencyInjection\AuroraExtension;

class AuroraBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new AuroraExtension();
    }
}