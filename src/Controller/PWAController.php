<?php

namespace Sindla\Bundle\AuroraBundle\Controller;

// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

// Aurora
use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;

class PWAController extends AbstractController
{
    public function __construct(private Environment $twig)
    {
    }

    /**
     * See src/Resources/config/routes/routes.yaml
     */
    public function progressiveWebApplication(Request $Request, ?int $width, ?int $height): Response
    {
        //var_dump($this->container->getParameter('aurora.pwa.offline'));die;

        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 0));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . $Request->getRequestUri()), function (ItemInterface $item) use ($Request) {
            /** @var PWA $PWA */
            $PWA = $this->container->get('aurora.pwa');

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
        return $this->twig->render('@Aurora/offline.html.twig');
    }
}
