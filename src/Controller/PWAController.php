<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

use Sindla\Bundle\AuroraBundle\Utils\AuroraPWA\AuroraPWA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PWAController extends AbstractController
{
    public function __construct(private Environment $twig)
    {
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
        /** @var AuroraPWA $PWA */
        $PWA = $this->container->get('aurora.pwa');

        return match ($Request->getPathInfo()) {
            '/manifest.json', '/manifest.webmanifest' => $PWA->manifestJSON($Request),
            '/browserconfig.xml', '/IEconfig.xml'     => $PWA->browserConfig($Request),
            '/pwa-main.js'                            => $PWA->mainJS($Request),
            '/sw.js', '/pwa-sw.js'                    => $PWA->serviceWorkerJS($Request),
            default                                   => $PWA->icon($Request),
        };
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function offline(): Response
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $rendered = $this->twig->render('@Aurora/offline.html.twig');
        $response = new Response($rendered);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }
}
