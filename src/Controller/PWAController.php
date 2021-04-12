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
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . $Request->getRequestUri()), function (ItemInterface $item) use ($Request) {
            /** @var PWA $PWA */
            $PWA = $this->get('aurora.pwa');

            // Manifest
            if (in_array($Request->getRequestUri(), ['manifest.json', '/manifest.json', 'manifest.webmanifest', '/manifest.webmanifest'])) {
                $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);
                return $PWA->manifestJSON($Request);
            } // MS browser config
            else if (in_array($Request->getRequestUri(), ['browserconfig.xml', '/browserconfig.xml', 'IEconfig.xml', '/IEconfig.xml'])) {
                $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);
                return $PWA->browserConfig($Request);
            } // Main JS
            else if (in_array($Request->getRequestUri(), ['pwa-main.js', '/pwa-main.js'])) {
                $item->expiresAfter(0);
                return $PWA->mainJS($Request);

            } // Service Worker JS
            else if (in_array($Request->getRequestUri(), ['sw.js', '/sw.js', 'pwa-sw.js', '/pwa-sw.js'])) {
                $item->expiresAfter(0);
                return $PWA->serviceWorkerJS($Request);

            } // Favicons
            else {
                $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);
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