<?php

namespace Sindla\Bundle\AuroraBundle\Utils\PWA;

use AllowDynamicProperties;
use MatthiasMullie\Minify;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

/**
 * Debug: php bin/console debug:container aurora.pwa
 */
#[AllowDynamicProperties]
class PWA
{
    private ?SessionInterface $session = null;

    public function __construct(
        private ContainerInterface $container,
        private RequestStack       $requestStack,
        private Environment        $twig
    )
    {
        if (method_exists($requestStack, 'getSession')) {
            try {
                $session = $requestStack->getSession();
            } catch (SessionNotFoundException) {
                $session = null;
            }

            if ($session instanceof SessionInterface) {
                $this->session = $session;
            }
        }

        if (null === $this->session) {
            $requestFromStack = null;

            if (method_exists($requestStack, 'getMainRequest')) {
                $requestFromStack = $requestStack->getMainRequest();
            } else if (method_exists($requestStack, 'getMasterRequest')) {
                $requestFromStack = $requestStack->getMasterRequest();
            } else if (method_exists($requestStack, 'getCurrentRequest')) {
                $requestFromStack = $requestStack->getCurrentRequest();
            }

            if ($requestFromStack instanceof Request && method_exists($requestFromStack, 'getSession')) {
                try {
                    $session = $requestFromStack->getSession();
                } catch (SessionNotFoundException) {
                    $session = null;
                }

                if ($session instanceof SessionInterface) {
                    $this->session = $session;
                }
            }
        }
    }

    /**
     * manifest.json | manifest.webmanifest
     */
    public function manifestJSON(Request $request): JsonResponse
    {
        $cache = $this->createCacheAdapter();

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . sha1($request->getRequestUri())), function (ItemInterface $item) use ($request) {

            $appName        = $this->container->getParameter('aurora.pwa.app_name');
            $appShortName   = $this->container->getParameter('aurora.pwa.app_short_name');
            $appDescription = $this->container->getParameter('aurora.pwa.app_description');
            $appThemeColor  = $this->container->getParameter('aurora.pwa.theme_color');
            $appBackground  = $this->container->getParameter('aurora.pwa.background_color');

            if (class_exists('\App\Service\AuroraService')) {
                $utils          = new \App\Service\AuroraService();
                $utils->request = $request;

                if (method_exists($utils, 'pwaAppName')) {
                    $appName = $utils->pwaAppName();
                }

                if (method_exists($utils, 'pwaAppShortName')) {
                    $appShortName = $utils->pwaAppShortName();
                }

                if (method_exists($utils, 'pwaDescription')) {
                    $appDescription = $utils->pwaDescription();
                }

                if (method_exists($utils, 'pwaThemeColor')) {
                    $appThemeColor = $utils->pwaThemeColor();
                }

                if (method_exists($utils, 'pwaBackgroundColor')) {
                    $appBackground = $utils->pwaBackgroundColor();
                }
            }

            $manifest = [
                'name'             => $appName,
                'short_name'       => $appShortName, // The short_name manifest member is used to specify a short name for your web application, which may be used when the full name is too long for the available space.
                'description'      => $appDescription,
                'id'               => $this->container->getParameter('aurora.pwa.start_url'), // When the browser sees a manifest that does not have an identity that matches an already installed PWA, it will treat it as a new PWA, even if it is served from the same URL as another PWA. But if it sees a manifest with an identity that matches the already installed PWA, it will treat that as the installed PWA.
                'start_url'        => $this->container->getParameter('aurora.pwa.start_url'),
                'display_override' => ['fullscreen', 'minimal-ui'],
                'display'          => $this->container->getParameter('aurora.pwa.display'),  // fullscreen
                'theme_color'      => $appThemeColor, // #RGB
                'background_color' => $appBackground, // #RGB
                'icons'            => []
            ];

            foreach ([36, 48, 72, 96, 144, 192, 512] as $iconSize) {
                $fileName = "android-icon-{$iconSize}x{$iconSize}.png";
                if (file_exists($this->container->getParameter('aurora.pwa.icons') . "/{$fileName}")) {
                    $manifest['icons'][] = [
                        'src'     => "/android-icon-{$iconSize}x{$iconSize}.png",
                        'sizes'   => "{$iconSize}x{$iconSize}",
                        'type'    => 'image/png',
                        'purpose' => 'any' // 'any', 'maskable'
                    ];
                } else {
                    trigger_error(sprintf('File %s not found.', $fileName), E_USER_NOTICE);
                }
            }

            $maskableIcon = $this->container->getParameter('aurora.pwa.icons') . '/android-icon-maskable.png';

            if (file_exists($maskableIcon) && 0 !== filesize($maskableIcon)) {
                [$maskableWidth, $maskableHeight] = (function_exists('getimagesize') ? getimagesize($maskableIcon) : [196, 196]);

                $manifest['icons'][] = [
                    'src'     => "/android-icon-maskable.png",
                    'sizes'   => "{$maskableWidth}x{$maskableHeight}",
                    'type'    => 'image/png',
                    'purpose' => 'maskable'
                ];
            } else {
                trigger_error(sprintf('File %s not found or size equals zero.', 'android-icon-maskable.png'), E_USER_NOTICE);
            }

            $Response = new JsonResponse($manifest);
            $Response->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $Response;
        });
    }

    /**
     * browserconfig.xml | IEconfig.xml
     */
    public function browserConfig(Request $request): Response
    {
        $cache = $this->createCacheAdapter();

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . sha1($request->getRequestUri())), function (ItemInterface $item) {
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

            $response = new Response($xml);
            $response->headers->set('Content-Type', 'text/xml');

            return $response;
        });
    }

    public function mainJS(Request $request): Response
    {
        if (!filter_var($this->container->getParameter('aurora.pwa.enabled') ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/javascript']);
        }

        $notificationInstallTheApp = 'Install the App';
        $notificationNewVersion    = 'A new version of the application is available. Reload to update.';
        $notificationReload        = 'Reload';

        if (class_exists('\App\Service\AuroraService')) {
            $utils          = new \App\Service\AuroraService();
            $utils->request = $request;

            if (method_exists($utils, 'transNotificationInstallTheApp')) {
                $notificationInstallTheApp = $utils->transNotificationInstallTheApp();
            }

            if (method_exists($utils, 'transNotificationNewVersion')) {
                $notificationNewVersion = $utils->transNotificationNewVersion();
            }

            if (method_exists($utils, 'transNotificationReload')) {
                $notificationReload = $utils->transNotificationReload();
            }
        }

        $automaticallyPrompt = true;

        if ($this->container->hasParameter('aurora.pwa.automatically_prompt')) {
            $rawAutomaticallyPrompt    = $this->container->getParameter('aurora.pwa.automatically_prompt');
            $parsedAutomaticallyPrompt = filter_var(
                $rawAutomaticallyPrompt,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if (null !== $parsedAutomaticallyPrompt) {
                $automaticallyPrompt = $parsedAutomaticallyPrompt;
            } else {
                $automaticallyPrompt = (bool)$rawAutomaticallyPrompt;
            }
        }

        $rendered = $this->twig->render('@Aurora/pwa-main.js.twig', [
            'pwaDebug'             => filter_var($this->container->getParameter('aurora.pwa.debug') ?? false, FILTER_VALIDATE_BOOLEAN),
            'pwaVersion'           => $this->version($request),
            'hostName'             => $request->getHost(),
            'automatically_prompt' => $automaticallyPrompt,
            'translations'         => [
                'notificationInstallTheApp' => addslashes($notificationInstallTheApp),
                'notificationNewVersion'    => addslashes($notificationNewVersion),
                'notificationReload'        => addslashes($notificationReload)
            ]
        ]);

        // Minify if not DEV
        if ('dev' !== $this->container->getParameter('kernel.environment')) {
            $minifier = new Minify\JS();
            $minifier->add($rendered);
            $rendered = $minifier->minify();
        }

        $response = new Response($rendered);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('max-age', 0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->set('Content-Type', 'text/javascript');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }

    public function serviceWorkerJS(Request $request): Response
    {
        if (!filter_var($this->container->getParameter('aurora.pwa.enabled') ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/javascript']);
        }

        $rendered = $this->twig->render('@Aurora/pwa-sw.js.twig', [
            'pwaDebug'                            => filter_var($this->container->getParameter('aurora.pwa.debug') ?? false, FILTER_VALIDATE_BOOLEAN),
            'pwaVersion'                          => $this->version($request),
            'hostName'                            => $request->getHost(),
            'precache'                            => "'" . implode("', '", array_unique(array_merge([$this->container->getParameter('aurora.pwa.start_url'), $this->container->getParameter('aurora.pwa.offline')], $this->container->getParameter('aurora.pwa.precache')))) . "'",
            'prevent_cache'                       => "'" . implode("', '", $this->container->getParameter('aurora.pwa.prevent_cache')) . "'",
            'prevent_cache_header_request_accept' => "'" . implode("', '", $this->container->getParameter('aurora.pwa.prevent_cache_header_request_accept') ?? []) . "'",
            'external_cache'                      => "/" . implode("/, /", $this->container->getParameter('aurora.pwa.external_cache')) . "/",
            'offline'                             => $this->container->getParameter('aurora.pwa.offline')
        ]);

        // Minify if not DEV
        if ('dev' !== $this->container->getParameter('kernel.environment')) {
            $minifier = new Minify\JS();
            $minifier->add($rendered);
            $rendered = $minifier->minify();
        }

        $response = new Response($rendered);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('max-age', 0);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->set('Content-Type', 'text/javascript');
        $response->headers->set('X-Do-Not-Minify', 'true');
        return $response;
    }

    public function version(Request $request): string
    {
        $serviceGit    = $this->container->get('aurora.git');
        $version       = $serviceGit->getHash();
        $versionAppend = $this->container->getParameter('aurora.pwa.version_append');

        $cookieSessionId = null;

        if (property_exists($request, 'cookies') && is_object($request->cookies)) {
            if (method_exists($request->cookies, 'get')) {
                $cookieSessionId = $request->cookies->get('PHPSESSID');
            }
        } else if (method_exists($request, 'cookies')) {
            $cookiesBag = $request->cookies();
            if (is_object($cookiesBag) && method_exists($cookiesBag, 'get')) {
                $cookieSessionId = $cookiesBag->get('PHPSESSID');
            }
        }

        if ($cookieSessionId && $this->session instanceof SessionInterface) {
            $sessionIdentifier = $this->session->get('PHPSESSID');
            if (null !== $sessionIdentifier && '' !== (string)$sessionIdentifier) {
                $version .= '_' . (string)$sessionIdentifier;
            }
        }

        if (0 === strpos($versionAppend, '!php/eval')) {
            preg_match('/`(.*)`/', $versionAppend, $match);
            if (isset($match[1]) && !empty($match[1])) {
                $version .= '_' . substr(sha1(eval("return " . trim($match[1], ';') . ";")), 0, 15);
            }
        }

        return $version;
    }

    /**
     * Favicon image
     *
     * @return image/x-icon
     */
    public function icon(Request $request): Response|BinaryFileResponse
    {
        $cache = $this->createCacheAdapter();

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . sha1($request->getRequestUri())), function (ItemInterface $item) use ($request) {
            $iconPath = $this->container->getParameter('aurora.pwa.icons') . $request->getRequestUri();

            if (!file_exists($iconPath)) {
                preg_match('/(\d+)x(\d+)/i', $request->getPathInfo(), $matches);
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

                trigger_error(sprintf('File %s not found.', $request->getPathInfo()), E_USER_NOTICE);

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

    private function _icon(string $iconPath): BinaryFileResponse
    {
        $response = new BinaryFileResponse($iconPath);
        $response->headers->set('Content-Length', filesize($iconPath));
        $response->headers->set('X-Backend-Hit', true);
        return $response;
    }

    private function createCacheAdapter(): AdapterInterface
    {
        $defaultLifetime = 'prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1;

        if (ApcuAdapter::isSupported()) {
            try {
                return new ApcuAdapter('', $defaultLifetime);
            } catch (CacheException) {
                // APCu support declared itself unavailable, fall back to an in-memory cache.
            }
        }

        return new ArrayAdapter($defaultLifetime);
    }
}
