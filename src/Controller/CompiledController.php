<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompiledController
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @see /src/Resources/config/routes/routes.yaml
     */
    public function cssJsFiles(Request $Request, string $fileName): Response
    {
        // %kernel.project_dir%/var/tmp
        $auroraTmpDir = $this->parameterBag->get('aurora.tmp');

        // Can be: /tmp/domain.tld/var/cache/dev/aurora/
        $auroraCacheDir = preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled'));

        $file = "{$auroraCacheDir}/{$fileName}";

        if (file_exists("$file")) {
            $fileContent = file_get_contents($file);
            $fileStatus  = Response::HTTP_OK;
        } else {
            $fileContent = "/* File {$fileName} not found */";
            $fileStatus  = Response::HTTP_NOT_FOUND;
        }

        $response = new Response($fileContent, $fileStatus);
        $response->headers->set('Content-Type', (('.js' == substr($fileName, -3)) ? 'text/javascript' : 'text/css'));
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }
}
