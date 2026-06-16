<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraPWA;

use MatthiasMullie\Minify;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

/**
 * Debug: php bin/console debug:container aurora.pwa
 */
class AuroraPWA
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Environment           $twig,
        #[Autowire(service: 'aurora.git')]
        private readonly AuroraGit             $git,
    ) {
    }

    /**
     * Read an "aurora.pwa.*" (or kernel) container parameter, returning $default when it is not defined.
     */
    private function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameterBag->has($name) ? $this->parameterBag->get($name) : $default;
    }

    /**
     * manifest.json | manifest.webmanifest
     */
    public function manifestJSON(Request $request): JsonResponse
    {
        $cache = $this->createCacheAdapter();

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . sha1($request->getRequestUri())), function (ItemInterface $item) use ($request) {

            $appName        = $this->parameter('aurora.pwa.app_name');
            $appShortName   = $this->parameter('aurora.pwa.app_short_name');
            $appDescription = $this->parameter('aurora.pwa.app_description');
            $appThemeColor  = $this->parameter('aurora.pwa.theme_color');
            $appBackground  = $this->parameter('aurora.pwa.background_color');

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
                'id'               => $this->parameter('aurora.pwa.start_url'), // When the browser sees a manifest that does not have an identity that matches an already installed PWA, it will treat it as a new AuroraPWA, even if it is served from the same URL as another PWA. But if it sees a manifest with an identity that matches the already installed PWA, it will treat that as the installed PWA.
                'start_url'        => $this->parameter('aurora.pwa.start_url'),
                'display_override' => ['fullscreen', 'minimal-ui'],
                'display'          => $this->parameter('aurora.pwa.display'),  // fullscreen
                'theme_color'      => $appThemeColor, // #RGB
                'background_color' => $appBackground, // #RGB
                'icons'            => []
            ];

            foreach ([36, 48, 72, 96, 144, 192, 512] as $iconSize) {
                $fileName = "android-icon-{$iconSize}x{$iconSize}.png";
                if (file_exists($this->parameter('aurora.pwa.icons') . "/{$fileName}")) {
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

            $maskableIcon = $this->parameter('aurora.pwa.icons') . '/android-icon-maskable.png';

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

            return
                new JsonResponse($manifest)
                    ->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
                        'TileColor'         => $this->parameter('aurora.pwa.theme_color') // #RGB
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
        if (!filter_var($this->parameter('aurora.pwa.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
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

        if ($this->parameterBag->has('aurora.pwa.automatically_prompt')) {
            $rawAutomaticallyPrompt    = $this->parameter('aurora.pwa.automatically_prompt');
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
            'pwaDebug'             => filter_var($this->parameter('aurora.pwa.debug', false), FILTER_VALIDATE_BOOLEAN),
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
        if ('dev' !== $this->parameter('kernel.environment')) {
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
        if (!filter_var($this->parameter('aurora.pwa.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/javascript']);
        }

        $rendered = $this->twig->render('@Aurora/pwa-sw.js.twig', [
            'pwaDebug'                            => filter_var($this->parameter('aurora.pwa.debug', false), FILTER_VALIDATE_BOOLEAN),
            'pwaVersion'                          => $this->version($request),
            'hostName'                            => $request->getHost(),
            'precache'                            => "'" . implode("', '", array_unique(array_merge([$this->parameter('aurora.pwa.start_url'), $this->parameter('aurora.pwa.offline')], $this->parameter('aurora.pwa.precache')))) . "'",
            'prevent_cache'                       => "'" . implode("', '", $this->parameter('aurora.pwa.prevent_cache')) . "'",
            'prevent_cache_header_request_accept' => "'" . implode("', '", $this->parameter('aurora.pwa.prevent_cache_header_request_accept', [])) . "'",
            'external_cache'                      => "/" . implode("/, /", $this->parameter('aurora.pwa.external_cache')) . "/",
            'offline'                             => $this->parameter('aurora.pwa.offline')
        ]);

        // Minify if not DEV
        if ('dev' !== $this->parameter('kernel.environment')) {
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
        $version = (string)$this->git->getHash();

        $versionAppend = $this->versionAppend($request);

        if ('' !== $versionAppend) {
            $version .= '_' . $versionAppend;
        }

        return $version;
    }

    /**
     * Resolve the optional, deploy-stable suffix appended to the PWA version.
     *
     * The returned value MUST be stable for the lifetime of a deployment (a build hash, a
     * release tag, an "APP_VERSION" value, ...). It must NEVER carry per-request, per-session,
     * or time-based data: the service worker embeds the version in its cache names
     * ("precache-<version>" / "runtime-<version>"), so a value that changes between requests
     * makes the browser treat the worker as updated and shows a false "new version available"
     * prompt on every revisit.
     *
     * Resolution order:
     *   1. An explicit, non-empty "aurora.pwa.version_append" string (e.g. a resolved
     *      "%env(APP_VERSION)%") is used as-is, sanitized to a safe token.
     *   2. Otherwise the host hook "App\Service\AuroraService::pwaVersionAppend(): string" is
     *      used when present (its return value is hashed to a short, bounded token).
     *   3. Otherwise the suffix is empty and the version is the git hash alone.
     *
     * The legacy "!php/eval `...`" form is intentionally NOT evaluated anymore: it executed
     * arbitrary configuration code and was frequently time-based, which churned the service
     * worker version on every request.
     */
    private function versionAppend(Request $request): string
    {
        $configured = (string)$this->parameter('aurora.pwa.version_append', '');

        if (str_starts_with($configured, '!php/eval')) {
            $configured = '';
        }

        if ('' !== $configured) {
            return $this->sanitizeVersionToken($configured);
        }

        if (class_exists('\App\Service\AuroraService')) {
            $utils          = new \App\Service\AuroraService();
            $utils->request = $request;

            if (method_exists($utils, 'pwaVersionAppend')) {
                return substr(sha1((string)$utils->pwaVersionAppend()), 0, 15);
            }
        }

        return '';
    }

    /**
     * Reduce an arbitrary string to a token that is safe to embed inside the service worker
     * cache names (and therefore inside a JavaScript string literal): letters, digits, ".",
     * "_" and "-".
     */
    private function sanitizeVersionToken(string $token): string
    {
        return (string)preg_replace('/[^A-Za-z0-9._-]/', '', $token);
    }

    /**
     * Favicon image (Content-Type: image/x-icon).
     */
    public function icon(Request $request): Response|BinaryFileResponse
    {
        $cache = $this->createCacheAdapter();

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__ . sha1($request->getRequestUri())), function (ItemInterface $item) use ($request) {
            $iconPath = $this->parameter('aurora.pwa.icons') . $request->getRequestUri();

            if (!file_exists($iconPath)) {
                preg_match('/(\d+)x(\d+)/i', $request->getPathInfo(), $matches);
                if (isset($matches[0]) && isset($matches[1]) && isset($matches[2]) && 0 != abs(intval($matches[1])) && 0 != abs(intval($matches[2]))) {
                    $iconPath = $this->parameter('aurora.pwa.icons') . "/android-icon-{$matches[1]}x{$matches[2]}.png";
                    if (file_exists($iconPath)) {
                        return $this->_icon($iconPath);
                    }

                    $iconPath = $this->parameter('aurora.pwa.icons') . "/apple-icon-{$matches[1]}x{$matches[2]}.png";
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
        $defaultLifetime = 'prod' == $this->parameter('kernel.environment') ? (60 * 60 * 24) : 1;

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
