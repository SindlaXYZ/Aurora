<?php

namespace Sindla\Bundle\AuroraBundle;

// Symfony
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * https://symfony.com/doc/current/bundles.html
 *
 * The "aurora" extension of the bundle: the settings are container parameters ("parameters: aurora.*"), see
 * src/Resources/schema/packages/aurora.yaml
 *
 * The services of the bundle use the services of FrameworkBundle (request_stack, parameter_bag, the route loaders) and of
 * TwigBundle (twig): the kernel registers these bundles when config/bundles.php does not
 */
#[RequiredBundle(FrameworkBundle::class)]
#[RequiredBundle(TwigBundle::class)]
class AuroraBundle extends AbstractBundle
{
    /**
     * The bundle keeps the "src/Resources/" directory structure: the applications import "@AuroraBundle/Resources/config/routes/routes.yaml"
     * and the templates of the "@Aurora" Twig namespace are read from src/templates/ (AbstractBundle assumes the root of the package)
     */
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/services.yaml');
    }
}
