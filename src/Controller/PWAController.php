<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class PWAController extends AbstractController
{
    /**
     * @Route("/favicon.ico")
     * @Route("/favicon-{width}x{height}.png")
     *
     * @Route("/android-icon-{width}x{height}.png")
     *
     * @Route("/apple-icon.png")
     * @Route("/apple-icon-precomposed.png")
     * @Route("/apple-icon-{width}x{height}.png")
     *
     * @Route("/ms-icon-{width}x{height}.png")
     *
     * @Route("/manifest.webmanifest")
     * @Route("/manifest.json")
     *
     * @Route("/pwa-main.js")
     * @Route("/pwa-sw.js")
     */
    public function progressiveWebApplication(Request $Request): Response
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

                // Main JS
            } else if (in_array($Request->getRequestUri(), ['pwa-main.js', '/pwa-main.js'])) {
                return $PWA->mainJS();

                // Service Worker JS
            } else if (in_array($Request->getRequestUri(), ['pwa-sw.js', '/pwa-sw.js'])) {
                return $PWA->serviceWorkerJS();

            } else {
                return $PWA->icon($Request);
            }
        });
    }

    /**
     * @Route("/pwa-offline", name="aurora_pwa_offline", methods={"GET","HEAD"})
     */
    public function offline(): Response
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }
        return $this->render('@Aurora/offline.html.twig');
    }
}