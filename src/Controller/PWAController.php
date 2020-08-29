<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class PWAController extends AbstractController
{
    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function progressiveWebApplication(Request $Request, ?int $width, ?int $height): Response
    {
        //var_dump($this->container->getParameter('aurora.pwa.offline'));die;

        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . $Request->getRequestUri()), function (ItemInterface $item) use ($Request) {
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);

            /** @var PWA $PWA */
            $PWA = $this->get('aurora.pwa');

            // Manifest
            if (in_array($Request->getRequestUri(), ['manifest.json', '/manifest.json', 'manifest.webmanifest', '/manifest.webmanifest'])) {
                return $PWA->manifestJSON();
            } // MS browser config
            else if (in_array($Request->getRequestUri(), ['browserconfig.xml', '/browserconfig.xml', 'IEconfig.xml', '/IEconfig.xml'])) {
                return $PWA->browserConfig();
            } // Main JS
            else if (in_array($Request->getRequestUri(), ['pwa-main.js', '/pwa-main.js'])) {
                return $PWA->mainJS();

            } // Service Worker JS
            else if (in_array($Request->getRequestUri(), ['pwa-sw.js', '/pwa-sw.js'])) {
                return $PWA->serviceWorkerJS();

            } // Favicons
            else {
                return $PWA->icon($Request);
            }
        });
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function offline(): Response
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }
        return $this->render('@Aurora/offline.html.twig');
    }
}