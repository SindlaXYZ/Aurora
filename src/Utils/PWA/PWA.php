<?php

namespace Sindla\Bundle\AuroraBundle\Utils\PWA;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
#use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Twig
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Environment;

// Minify
use MatthiasMullie\Minify;

/**
 * Debug: php bin/console debug:container aurora.pwa
 *
 * @package AuroraBundle\Utils
 */
class PWA
{
    /** @var ContainerInterface */
    private $container;

    private $twig;

    public function __construct(ContainerInterface $container, Environment $twig)
    {
        $this->container     = $container;
        $this->twig          = $twig;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
    }

    /**
     * manifest.json | manifest.webmanifest
     *
     * Do not cache it!
     *
     * @return JsonResponse
     */
    public function manifestJSON()
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) {
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);

            $manifest = [
                'name'             => $this->container->getParameter('aurora.pwa.app_name'),
                'short_name'       => $this->container->getParameter('aurora.pwa.app_short_name'),
                'description'      => $this->container->getParameter('aurora.pwa.app_description'),
                'start_url'        => $this->container->getParameter('aurora.pwa.start_url'),
                'display'          => $this->container->getParameter('aurora.pwa.display'),
                'theme_color'      => $this->container->getParameter('aurora.pwa.theme_color'),
                'background_color' => $this->container->getParameter('aurora.pwa.background_color'),
                'icons'            => []
            ];

            foreach ([36, 48, 72, 96, 144, 192, 512] as $iconSize) {
                if (file_exists($this->container->getParameter('aurora.pwa.icons') . "/android-icon-{$iconSize}x{$iconSize}.png")) {
                    $manifest['icons'][] = [
                        'src'   => "/android-icon-{$iconSize}x{$iconSize}.png",
                        'sizes' => "{$iconSize}x{$iconSize}",
                        'type'  => 'image/png'
                    ];
                }
            }

            $Response = new JsonResponse($manifest);
            $Response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $Response;
        });
    }

    public function mainJS()
    {
        $rendered = $this->twig->render('@Aurora/pwa-main.js.twig', []);

        // Minify if not DEV
        if ('dev' !== $this->container->getParameter('kernel.environment')) {
            $minifier = new Minify\JS();
            $minifier->add($rendered);
            $rendered = $minifier->minify();
        }

        $response = new \Symfony\Component\HttpFoundation\Response($rendered);
        $response->headers->set('Content-Type', 'text/javascript');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }

    public function serviceWorkerJS()
    {
        $serviceGit = $this->container->get('aurora.git');

        $rendered = $this->twig->render('@Aurora/pwa-sw.js.twig', [
            'precache'       => "'" . implode("', '", array_unique(array_merge([$this->container->getParameter('aurora.pwa.offline')], $this->container->getParameter('aurora.pwa.precache')))) . "'",
            'prevent_cache'  => "'" . implode("', '", $this->container->getParameter('aurora.pwa.prevent_cache')) . "'",
            'external_cache' => "/" . implode("/, /", $this->container->getParameter('aurora.pwa.external_cache')) . "/",
            'offline'        => $this->container->getParameter('aurora.pwa.offline'),
            'build'          => $serviceGit->getHash()
        ]);

        // Minify if not DEV
        if ('dev' !== $this->container->getParameter('kernel.environment')) {
            $minifier = new Minify\JS();
            $minifier->add($rendered);
            $rendered = $minifier->minify();
        }

        $response = new \Symfony\Component\HttpFoundation\Response($rendered);
        $response->headers->set('Content-Type', 'text/javascript');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }

    public function icon(Request $Request)
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) use ($Request) {
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? 60 : 0);
            $iconPath = $this->container->getParameter('aurora.pwa.icons') . $Request->getRequestUri();

            if (!file_exists($iconPath)) {
                return new Response(
                    base64_decode('AAABAAEAEBAQAAEABAAoAQAAFgAAACgAAAAQAAAAIAAAAAEABAAAAAAAgAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAA/4QAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAREQAAEAAAEBABAAAQAAAQEAEAABAAABAQAQAAEAEREBABAREQAQAQEAEBABABABAQAQEAEAEAEBABAQAQAQAQEREBABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAA//8AAP//AAD//wAA9D0AAPW9AAD1vQAA9b0AAIWhAAC1rQAAta0AALWtAAC0LQAA//8AAP//AAD//wAA'),
                    Response::HTTP_OK,
                    ['content-type' => 'image/x-icon']
                );
            }

            $Response = new BinaryFileResponse($iconPath);
            $Response->headers->set('Content-Length', filesize($iconPath));
            $Response->headers->set('X-Backend-Hit', true);
            return $Response;
        });
    }
}