<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

class PWAController
{
    /**
     * The profiler is optional: it is not registered in production
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly AuroraPWA   $pwa,
        private readonly ?Profiler   $profiler = null,
    ) {
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     *
     * Dispatched by the path: the request URI contains the query string (and the base path), so "/pwa-sw.js?v2" (e.g. an asset
     * version) used to be answered with the favicon. AuroraPWA caches the manifest, the browser config and the icons itself: the
     * response cache used here required APCu (500 when it is not enabled), was shared by all the hosts and could not store the icons.
     */
    public function progressiveWebApplication(Request $Request, ?int $width, ?int $height): Response
    {
        return match ($Request->getPathInfo()) {
            '/manifest.json', '/manifest.webmanifest' => $this->pwa->manifestJSON($Request),
            '/browserconfig.xml', '/IEconfig.xml'     => $this->pwa->browserConfig($Request),
            '/pwa-main.js'                            => $this->pwa->mainJS($Request),
            '/sw.js', '/pwa-sw.js'                    => $this->pwa->serviceWorkerJS($Request),
            default                                   => $this->pwa->icon($Request),
        };
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function offline(): Response
    {
        $this->profiler?->disable();

        $rendered = $this->twig->render('@Aurora/offline.html.twig');
        $response = new Response($rendered);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }
}
