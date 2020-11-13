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
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\HttpFoundation\Session\Session;

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

    /** @var Session */
    private $session;

    private $twig;

    public function __construct(ContainerInterface $container, Session $session, Environment $twig)
    {
        $this->container     = $container;
        $this->session       = $session;
        $this->twig          = $twig;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
    }

    /**
     * manifest.json | manifest.webmanifest
     *
     * @return JsonResponse
     */
    public function manifestJSON(Request $Request)
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) {
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? (60 * 60 * 24) : 0);

            $manifest = [
                'name'             => $this->container->getParameter('aurora.pwa.app_name'),
                'short_name'       => $this->container->getParameter('aurora.pwa.app_short_name'),
                'description'      => $this->container->getParameter('aurora.pwa.app_description'),
                'start_url'        => $this->container->getParameter('aurora.pwa.start_url'),
                'display'          => $this->container->getParameter('aurora.pwa.display'),  // fullscreen
                'theme_color'      => $this->container->getParameter('aurora.pwa.theme_color'), // #RGB
                'background_color' => $this->container->getParameter('aurora.pwa.background_color'), // #RGB
                'icons'            => []
            ];

            foreach ([36, 48, 72, 96, 144, 192, 512] as $iconSize) {
                if (file_exists($this->container->getParameter('aurora.pwa.icons') . "/android-icon-{$iconSize}x{$iconSize}.png")) {
                    $manifest['icons'][] = [
                        'src'     => "/android-icon-{$iconSize}x{$iconSize}.png",
                        'sizes'   => "{$iconSize}x{$iconSize}",
                        'type'    => 'image/png',
                        'purpose' => 'any' // 'any', 'maskable', 'any maskable'
                    ];
                }
            }

            $maskableIcon = $this->container->getParameter('aurora.pwa.icons') . '/android-icon-maskable.png';
            if (file_exists($maskableIcon)) {
                [$maskableWidth, $maskableHeight] = (function_exists('getimagesize') ? getimagesize($maskableIcon) : [196, 196]);

                $manifest['icons'][] = [
                    'src'     => "/android-icon-maskable.png",
                    'sizes'   => "{$maskableWidth}x{$maskableHeight}",
                    'type'    => 'image/png',
                    'purpose' => 'any maskable'
                ];
            }

            $Response = new JsonResponse($manifest);
            $Response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $Response;
        });
    }

    /**
     * browserconfig.xml | IEconfig.xml
     *
     * @return XML
     */
    public function browserConfig(Request $Request)
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) {
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? (60 * 60 * 24) : 0);

            $encoder       = new XmlEncoder();
            $browserConfig = [
                'msapplication' => [
                    'tile' => [
                        'square70x70logo'   => ['@src' => '/ms-icon-70x70.png'],
                        'square150x150logo' => ['@src' => '/ms-icon-150x150.png'],
                        'square310x310logo' => ['@src' => '/ms-icon-310x310.png'],
                        'TileColor'         => $this->container->getParameter('aurora.pwa.theme_color') // #RGB
                    ]
                ]
            ];

            # https://symfony.com/doc/current/components/serializer.html#id1
            $xml = $encoder->encode($browserConfig, 'xml', [
                'xml_version'        => '1.0',
                'xml_encoding'       => 'utf-8',
                'xml_root_node_name' => 'browserconfig'
            ]);

            $Response = new Response($xml);
            $Response->headers->set('Content-Type', 'text/xml');

            return $Response;
        });
    }

    public function mainJS(Request $Request)
    {
        $rendered = $this->twig->render('@Aurora/pwa-main.js.twig', [
            'build'                => $this->_build($Request),
            'automatically_prompt' => ($this->container->hasParameter('aurora.pwa.automatically_prompt') ? boolval($this->container->getParameter('aurora.pwa.automatically_prompt')) : true)
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

    public function serviceWorkerJS(Request $Request)
    {
        $rendered = $this->twig->render('@Aurora/pwa-sw.js.twig', [
            'precache'       => "'" . implode("', '", array_unique(array_merge([$this->container->getParameter('aurora.pwa.start_url'), $this->container->getParameter('aurora.pwa.offline')], $this->container->getParameter('aurora.pwa.precache')))) . "'",
            'prevent_cache'  => "'" . implode("', '", $this->container->getParameter('aurora.pwa.prevent_cache')) . "'",
            'external_cache' => "/" . implode("/, /", $this->container->getParameter('aurora.pwa.external_cache')) . "/",
            'offline'        => $this->container->getParameter('aurora.pwa.offline'),
            'build'          => $this->_build($Request)
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
            $item->expiresAfter(('dev' !== $this->container->getParameter('kernel.environment')) ? (60 * 60 * 24) : 0);
            $iconPath = $this->container->getParameter('aurora.pwa.icons') . $Request->getRequestUri();

            if (!file_exists($iconPath)) {
                preg_match('/(\d+)x(\d+)/i', $Request->getPathInfo(), $matches);
                if (isset($matches[0]) && isset($matches[1]) && isset($matches[2]) && 0 != abs(intval($matches[1])) && 0 != abs(intval($matches[2]))) {
                    $iconPath = $this->container->getParameter('aurora.pwa.icons') . "/android-icon-{$matches[1]}x{$matches[2]}.png";
                    if (file_exists($iconPath)) {
                        return $this->_icon($iconPath);
                    }

                    $iconPath = $this->container->getParameter('aurora.pwa.icons') . "/apple-icon-{$matches[1]}x{$matches[2]}.png";
                    if (file_exists($iconPath)) {
                        return $this->_icon($iconPath);
                    }
                }

                // Return 404 icon
                return new Response(
                    base64_decode('AAABAAEAEBAQAAEABAAoAQAAFgAAACgAAAAQAAAAIAAAAAEABAAAAAAAgAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAA/4QAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABAREQAAEAAAEBABAAAQAAAQEAEAABAAABAQAQAAEAEREBABAREQAQAQEAEBABABABAQAQEAEAEAEBABAQAQAQAQEREBABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD//wAA//8AAP//AAD//wAA9D0AAPW9AAD1vQAA9b0AAIWhAAC1rQAAta0AALWtAAC0LQAA//8AAP//AAD//wAA'),
                    Response::HTTP_OK,
                    ['content-type' => 'image/x-icon']
                );
            }

            return $this->_icon($iconPath);
        });
    }

    private function _icon(string $iconPath)
    {
        $Response = new BinaryFileResponse($iconPath);
        $Response->headers->set('Content-Length', filesize($iconPath));
        $Response->headers->set('X-Backend-Hit', true);
        return $Response;
    }

    /**
     * PWA build version based on last git commit hash and PHPSESSID
     *
     * @param Request $Request
     * @return string
     */
    private function _build(Request $Request)
    {
        $serviceGit = $this->container->get('aurora.git');
        return sha1(($serviceGit->getHash() . ($Request->cookies->get('PHPSESSID') ?? $this->session->get('PHPSESSID'))));
    }
}