<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CompiledController extends AbstractController
{
    /**
     * @see /src/Resources/config/routes/routes.yaml
     */
    public function cssJsFiles(Request $Request, string $fileName): Response
    {
        // %kernel.project_dir%/var/tmp
        $auroraTmpDir = $this->container->getParameter('aurora.tmp');

        // Can be: /tmp/domain.tld/var/cache/dev/aurora/
        $auroraCacheDir = preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled'));

        $file = "{$auroraCacheDir}/{$fileName}";

        if (file_exists("$file")) {
            $fileContent = file_get_contents($file);
        } else {
            $fileContent = "/* File {$fileName} not found */";
        }

        $response = new \Symfony\Component\HttpFoundation\Response($fileContent);
        $response->headers->set('Content-Type', (('.js' == substr($fileName, -3)) ? 'text/javascript' : 'text/css'));
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }
}