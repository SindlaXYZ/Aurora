<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Twig;

// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

// Twig
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Environment;

// Minify
use MatthiasMullie\Minify;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;
use Sindla\Bundle\AuroraBundle\Utils\Git\Git;
use Sindla\Bundle\AuroraBundle\Utils\Sanitizer\Sanitizer;

class UtilityExtension extends AbstractExtension
{
    /** @var Container */
    protected Container $container;

    /** @var RequestStack */
    protected RequestStack $request;

    /** @var Environment */
    private Environment $twig;

    /** @var string|null */
    private ?string $nonce = null;

    /**
     * UtilityExtension constructor
     *
     * @param Container    $serviceContainer
     * @param RequestStack $Request
     * @param Environment  $twig
     */
    public function __construct(Container $serviceContainer, RequestStack $Request, Environment $twig)
    {
        $this->container = $serviceContainer;
        $this->request   = $Request;
        $this->twig      = $twig;
    }

    ##########################################################################################################################################################################################
    ###   FILTERS     ########################################################################################################################################################################

    /**
     * Twig filters
     */
    public function getFilters()
    {
        return [
            /** {{ '1987-12-20'|aurora.age }} */
            new TwigFilter('age', [$this, 'filterAge']),

            new TwigFilter('replace_array', [$this, 'filterReplaceArray'])
        ];
    }

    /**
     * @param \DateTime $date
     * @return int
     */
    public function filterAge(\DateTime $date)
    {
        $referenceDate           = date('01-01-Y');
        $referenceDateTimeObject = new \DateTime($referenceDate);

        $diff = $referenceDateTimeObject->diff($date);

        return $diff->y;
    }

    public function filterReplaceArray(string $subject, array $search, string $replace)
    {
        return str_replace($search, $replace, $subject);
    }

    ##########################################################################################################################################################################################
    ###   FUNCTIONS     ######################################################################################################################################################################

    /**
     * Twig functions
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('build', [$this, 'getBuild']),
            new TwigFunction('buildDate', [$this, 'getBuildDate']),

            /** {{ aurora.hash(2) }} */
            new TwigFunction('hash', [$this, 'getHash']),

            /** {{ aurora.sha1('my string to sha1') }} */
            new TwigFunction('sha1', [$this, 'getSha1']),
            new TwigFunction('ip2Country', [$this, 'ip2Country']),
            new TwigFunction('ip2County', [$this, 'ip2County']),
            new TwigFunction('ip2City', [$this, 'ip2City']),
            new TwigFunction('compressCss', [$this, 'compressCss']),
            new TwigFunction('compressJs', [$this, 'compressJs']),
            new TwigFunction('compressCSSJS', [$this, 'compressCSSJS']),

            new TwigFunction('manifest', [$this, 'manifest']),

            // {{ aurora.pwa(app.request, app.debug) }}
            new TwigFunction('pwa', [$this, 'pwa']),

            new TwigFunction('pwa.version', [$this, 'pwaVersion']),

            // {{ aurora.pwa.delete(app.request, app.debug) }}
            new TwigFunction('pwa.delete', [$this, 'pwaDelete']),

            // {{ aurora.pwa.unregister(app.request, app.debug) }}
            new TwigFunction('pwa.unregister', [$this, 'pwaUnregister']),

            new TwigFunction('dnsPrefetch', [$this, 'dnsPrefach']),

            new TwigFunction('linkRelDnsPrefetch', [$this, 'linkRelDnsPrefetch']),
            new TwigFunction('linkRelPreload', [$this, 'linkRelPreload']),

            // {{ aurora.nonce() }}
            new TwigFunction('nonce', [$this, 'getNonce']),
        ];
    }

    /**
     * Render and output twig template
     */
    public function manifest(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/manifest.html.twig', [
            'host'        => $Request->getHost(),
            'pwa'         => (bool)($Request->isSecure() || preg_match('/(.*\.localhost$|^localhost$)/i', $Request->getHost())),
            'theme_color' => $this->container->getParameter('aurora.pwa.theme_color'),
            'build'       => $this->getBuild(),
            'debug'       => $debug
        ]);
    }

    /**
     * Render and output twig template
     */
    public function pwa(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/pwa.html.twig', [
            'host'        => $Request->getHost(),
            'pwa'         => (bool)($Request->isSecure() || preg_match('/(.*\.localhost$|^localhost$)/i', $Request->getHost())),
            'theme_color' => $this->container->getParameter('aurora.pwa.theme_color'),
            'build'       => $this->getBuild(),
            'pwaVersion'  => $this->pwaVersion($Request),
            'debug'       => $debug
        ]);
    }

    public function pwaVersion(Request $Request)
    {
        /** @var PWA $PWA */
        $PWA = $this->container->get('aurora.pwa');

        return $PWA->version($Request);
    }

    public function pwaDelete(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/pwa.delete.html.twig', [
            'host'       => $Request->getHost(),
            'pwa'        => (bool)($Request->isSecure() || preg_match('/(.*\.localhost$|^localhost$)/i', $Request->getHost())),
            'build'      => $this->getBuild(),
            'pwaVersion' => $this->pwaVersion($Request),
            'debug'      => $debug
        ]);
    }

    public function pwaUnregister(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/pwa.unregister.html.twig', [
            'host'       => $Request->getHost(),
            'pwa'        => (bool)($Request->isSecure() || preg_match('/(.*\.localhost$|^localhost$)/i', $Request->getHost())),
            'build'      => $this->getBuild(),
            'pwaVersion' => $this->pwaVersion($Request),
            'debug'      => $debug
        ]);
    }

    public function dnsPrefetch()
    {
        // Since 2020-12-18
        trigger_error('Method aurora.dnsPrefetch() is deprecated. Use aurora.linkRelDnsPrefetch() instead.', E_USER_DEPRECATED);
        $this->linkRelDnsPrefetch();
    }

    public function linkRelDnsPrefetch()
    {
        $dnsPrefetches = $this->container->getParameter('aurora.dns_prefetch');
        $html          = '';
        foreach ($dnsPrefetches as $dnsPrefetch) {
            $html .= "<link rel='dns-prefetch' href='//{$dnsPrefetch}' />";
        }

        echo $html;
    }

    /**
     * <link rel="preload" href="{{ asset('static/vendor/fontawesome/5.15.1/webfonts/fa-solid-900.woff2') }}" as="font" type="font/woff2" crossorigin/>
     *
     * Usage:
     *      {{ aurora.linkRelPreload([{'asset' : asset('static/vendor/fontawesome/5.15.1/webfonts/fa-solid-900.woff2'), 'as':'font', 'type': 'font/woff2', 'crossorigin': true}]) }}
     *
     * @param array $assets
     */
    public function linkRelPreload(array $assets)
    {
        $html = '';
        foreach ($assets as $asset) {
            $html .= "<link rel='preload' href='{$asset['asset']}' as='{$asset['as']}' type='{$asset['type']}' " . (isset($asset['crossorigin']) && $asset['crossorigin'] ? 'crossorigin' : '') . " />";
        }
        echo $html;
    }

    public function getBuild($limit = null)
    {
        $serviceGit = $this->container->get('aurora.git');
        $build      = $serviceGit->getHash();
        return substr($build, 0, ($limit ? $limit : strlen($build)));
    }

    public function getBuildDate()
    {
        $serviceGit = $this->container->get('aurora.git');
        return $serviceGit->getDate();
    }

    public function getHash($size = 24)
    {
        $size = min($size, 40);

        return substr(sha1(microtime() . time() . uniqid()), 0, $size);
    }

    public function getSha1($string)
    {
        return sha1($string);
    }

    public function ip(Request $Request)
    {
        $Client = $this->container->get('aurora.client');
        return $Client->ip($Request);
    }

    public function ip2Country(Request $Request)
    {
        $Client = $this->container->get('aurora.client');
        return $Client->ip2CountryCode($this->ip($Request));
    }

    public function ip2County(Request $Request)
    {
        $Client = $this->container->get('aurora.client');
        return $Client->ip2CityCounty($this->ip($Request));
    }

    public function ip2City(Request $Request)
    {
        $Client = $this->container->get('aurora.client');
        return $Client->ip2CityName($this->ip($Request));
    }

    /**
     * Minify the CSS into one single file
     * Save (if not exists) the minified file using the last GIT branch hash tag
     *
     * https://github.com/matthiasmullie/minify
     *
     * @param       $Request
     * @param mixed ...$assets
     */
    public function compressCss(Request $Request, $combine, $minify, ...$assets)
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated. Use compressCSSJS() instead.', E_USER_DEPRECATED);

        $serviceGit = $this->container->get('aurora.git');

        if (!$combine && !$minify) {
            foreach ($assets as $asset) {
                $asset = trim($asset);
                if (strlen($asset) > 3) {
                    if (!preg_match('/http:|https:/', $asset)) {
                        $asset = $asset . '?v=' . (('dev' === $this->container->getParameter('kernel.environment')) ? uniqid() : $serviceGit->getHash());
                    }
                    echo "\n\t" . '<link type="text/css" rel="stylesheet" href="' . $asset . '" />';
                }
            }
        } else {
            $auroraRootDir = $this->container->getParameter('aurora.root'); // %kernel.project_dir%
            $auroraTmpDir  = $this->container->getParameter('aurora.tmp');  // %kernel.project_dir%/var/tmp

            // '%kernel.project_dir%/public/static
            // $auroraStaticDir = $this->container->getParameter('aurora.static');

            // Can be: %kernel.project_dir%/var/tmp/compiled
            $staticServerDir = 0 ? preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled')) : preg_replace('~//+~', '/', ($auroraRootDir . '/public/static/compiled'));
            $staticWebDir    = 0 ? '/aurora/compiled' : '/static/compiled';

            if ('dev' === $this->container->getParameter('kernel.environment') && !is_dir($staticServerDir) && !mkdir($staticServerDir, 0777, true)) {
                throw new \RuntimeException("[AURORA] Cannot create cache dir `{$staticServerDir}`");
            }

            $combined = '/*' . date('Y-m-d H:i:s') . '*/';
        }
    }

    /**
     * Minify the JS into one single file
     * Save (if not exists) the minified file using the last GIT branch hash tag
     *
     * https://github.com/matthiasmullie/minify
     * https://github.com/tedious/JShrink
     *
     * @param       $Request
     * @param mixed ...$assets
     */
    public function compressJs(Request $Request, $combine, $minify, ...$assets)
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated. Use compressCSSJS() instead.', E_USER_DEPRECATED);

        // TODO: external JS files : preg_match('/http:|https:|ftp:/', $asset)

        $serviceGit = $this->container->get('aurora.git');

        if (!$combine && !$minify) {
            foreach ($assets as $asset) {
                $asset = trim($asset);
                if (strlen($asset) > 3) {
                    if (!preg_match('/http:|https:/', $asset)) {
                        $asset = $asset . '?v=' . (('dev' === $this->container->getParameter('kernel.environment')) ? uniqid() : $serviceGit->getHash());
                    }
                    echo "\n\t" . '<script src="' . $asset . '" nonce="' . $this->getNonce() . '" nonce="' . $this->getNonce() . '"></script>';
                }
            }
        } else {
            $auroraRootDir = $this->container->getParameter('aurora.root'); // %kernel.project_dir%
            $auroraTmpDir  = $this->container->getParameter('aurora.tmp');  // %kernel.project_dir%/var/tmp

            // '%kernel.project_dir%/public/static
            // $auroraStaticDir = $this->container->getParameter('aurora.static');

            // Can be: %kernel.project_dir%/var/tmp/compiled
            $staticServerDir = 0 ? preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled')) : preg_replace('~//+~', '/', ($auroraRootDir . '/public/static/compiled'));
            $staticWebDir    = 0 ? '/aurora/compiled' : '/static/compiled';

            if ('dev' === $this->container->getParameter('kernel.environment') && !is_dir($staticServerDir) && !mkdir($staticServerDir, 0777, true)) {
                throw new \RuntimeException("[AURORA] Cannot create cache dir `{$staticServerDir}`");
            }

            $combined = '/*' . date('Y-m-d H:i:s') . '*/';
            foreach ($assets as $asset) {
                $asset = trim($asset);
                if (strlen($asset) > 3) {
                    $assetContent = file_get_contents($auroraRootDir . '/public/' . $asset);

                    if ($combine && $minify) {
                        $combined .= \JShrink\Minifier::minify($assetContent, ['flaggedComments' => false]) . ';';
                    } else {
                        $sha1         = sha1($asset . $serviceGit->getHash()) . '.js';
                        $minifiedCode = \JShrink\Minifier::minify($assetContent, ['flaggedComments' => false]);
                        if ('dev' === $this->container->getParameter('kernel.environment') || !file_exists("{$staticServerDir}/{$sha1}")) {
                            file_put_contents("{$staticServerDir}/{$sha1}", '/*' . date('Y-m-d H:i:s') . '*/' . $minifiedCode);
                        }
                        echo "\n\t" . '<script src="' . $staticWebDir . '/' . $sha1 . '" nonce="' . $this->getNonce() . '"></script>';
                    }
                }
            }

            if ($combine && $minify) {
                $sha1 = sha1($serviceGit->getHash()) . '.js';
                if ('dev' === $this->container->getParameter('kernel.environment') || !file_exists("{$staticServerDir}/{$sha1}")) {
                    file_put_contents("{$staticServerDir}/{$sha1}", $combined);
                }
                echo "\n\t" . '<script src="' . $staticWebDir . '/' . $sha1 . '" nonce="' . $this->getNonce() . '"></script>';
            }
        }
    }

    /**
     * Minify CSS|JS into one single file
     *
     * https://github.com/matthiasmullie/minify
     * https://github.com/tedious/JShrink
     *
     * @param Request $Request
     * @param string  $assetType
     * @param bool    $combineAndMinify
     * @param mixed   ...$assets
     * @throws \Exception
     */
    public function compressCSSJS(Request $Request, string $assetType, bool $combineAndMinify = false, ...$assets)
    {
        $auroraRootDir = $this->container->getParameter('aurora.root'); // %kernel.project_dir%

        /** @var Sanitizer $serviceSanitizer */
        $serviceSanitizer = $this->container->get('aurora.sanitizer');

        /** @var Git $serviceGit */
        $serviceGit = $this->container->get('aurora.git');

        // Can be: %kernel.project_dir%/var/tmp/compiled
        # $auroraTmpDir  = $this->container->getParameter('aurora.tmp');  // %kernel.project_dir%/var/tmp
        # $staticServerDir = (0 ? preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled')) : preg_replace('~//+~', '/', ($auroraRootDir . '/public/static/compiled')));
        # $staticWebDir    = (0 ? '/aurora/compiled' : '/static/compiled');

        $onDev = boolval('dev' === $this->container->getParameter('kernel.environment'));

        if ($combineAndMinify) {
            $combineAndMinifyOutputContentHead = '';
            $combineAndMinifyOutputContent     = '';
            $combineAndMinifyOutputFileName    = (sha1(json_encode($assets) . $serviceGit->getHash()) . ('css' == $assetType ? '.css' : '.js'));
            $combineAndMinifyOutputAbsPath     = "{$auroraRootDir}/public/static/compiled/{$combineAndMinifyOutputFileName}";
            $combineAndMinifyOutputWebPath     = "/static/compiled/{$combineAndMinifyOutputFileName}";
        }

        foreach ($assets as $index => $assetWebPath) {
            if (strlen($assetWebPath) > 3) {

                $assetWebPath  = trim($assetWebPath);                             // relative to domain root, eg: static/css/main.css or external file
                $assetAbsPath  = "{$auroraRootDir}/public/{$assetWebPath}";       // relative to server dirs, eg: /srv/domain.tld/public/static/css/main.css
                $assetBasename = basename($assetWebPath);                         // main.css
                $assetBaseDir  = str_ireplace($assetBasename, '', $assetWebPath); // static/css/

                // No combine in one single file, and do not minify
                if (!$combineAndMinify) {
                    if (!preg_match('/http:|https:|ftp:/', $assetWebPath)) {
                        $assetWebPath = ($assetWebPath . '?v=' . ($onDev ? uniqid() : $serviceGit->getHash()));
                    }

                    if ('css' == $assetType) {
                        echo "\n\t" . '<link type="text/css" rel="stylesheet" href="' . $assetWebPath . '" />';
                    }

                    if ('js' == $assetType) {
                        echo "\n\t" . '<script src="' . $assetWebPath . '" nonce="' . $this->getNonce() . '"></script>';
                    }
                }

                /**
                 * TODO: some files might have @import or @charset, move these on top of compiled css file
                 */

                if ($combineAndMinify && ($onDev || !file_exists($combineAndMinifyOutputAbsPath))) {
                    // Load local files
                    if (!preg_match('/http:|https:|ftp:/', $assetWebPath)) {

                        $assetContent = file_get_contents($assetAbsPath);
                        if ('js' == $assetType) {
                            /**
                             * Fix for the following bug: `a || b` will be replaced with `a|\nb` (only if there are spaces around ||)
                             */
                            $assetContent = str_replace(' || ', '||', $assetContent);

                            /**
                             * HightChart bug: `/(NaN| {2}|^$)/` will be replaced with `/(NaN|{2}|^$)/`
                             */
                            $assetContent = str_replace('| {', '\n{', $assetContent);
                        }

                        $combineAndMinifyOutputContent .= ('css' == $assetType
                            ? $serviceSanitizer->cssMinify($assetContent, $assetWebPath)
                            : \JShrink\Minifier::minify($assetContent, ['flaggedComments' => false]) . ';'
                        );
                        // Load external files
                    } else {
                        if ('css' == $assetType) {
                            $combineAndMinifyOutputContentHead .= '@import url("' . $assetWebPath . '");';
                        } else {
                            $njs = 'newScript' . substr(mt_rand(0, 999) . sha1(microtime() . mt_rand(0, 999)), 0, 6);
                            $combineAndMinifyOutputContent .= "var {$njs} = document.createElement('script'); {$njs}.type = 'text/javascript'; {$njs}.src = '{$assetWebPath}'; document.getElementsByTagName('head')[0].appendChild({$njs});";
                        }
                    }
                }
            }
        }

        if ($combineAndMinify) {
            if ($onDev || !file_exists($combineAndMinifyOutputAbsPath)) {
                file_put_contents($combineAndMinifyOutputAbsPath, '/*' . date('Y-m-d H:i:s') . '*/' . ($combineAndMinifyOutputContentHead . $combineAndMinifyOutputContent));
            }

            if ('css' == $assetType) {
                echo "\n\t" . '<link type="text/css" rel="stylesheet" href="' . $combineAndMinifyOutputWebPath . '" />';
            }

            if ('js' == $assetType) {
                echo "\n\t" . '<script src="' . $combineAndMinifyOutputWebPath . '" nonce="' . $this->getNonce() . '"></script>';
            }
        }
    }

    public function getNonce()
    {
        // generation occurs only when $this->nonce is still null
        if (!$this->nonce) {
            $this->nonce = base64_encode(random_bytes(20));
        }

        return $this->nonce;
    }
}