<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Twig;

// Symfony
use Sindla\Bundle\AuroraBundle\Utils\Git\Git;
use Sindla\Bundle\AuroraBundle\Utils\Sanitizer\Sanitizer;
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

            // {{ aurora.pwa.delete(app.request, app.debug) }}
            new TwigFunction('pwa.delete', [$this, 'pwaDelete']),

            new TwigFunction('dnsPrefetch', [$this, 'dnsPrefach']),

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
            'debug'       => $debug
        ]);
    }

    public function pwaDelete(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/pwa.delete.html.twig', [
            'debug' => $debug
        ]);
    }

    public function dnsPrefach()
    {
        $dnsPrefetches = $this->container->getParameter('aurora.dns_prefetch');
        $html          = '';
        foreach ($dnsPrefetches as $dnsPrefetch) {
            $html .= "<link rel='dns-prefetch' href='//{$dnsPrefetch}' />";
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
    public function compressCssV1(Request $Request, $combine, $minify, ...$assets)
    {
        $serviceGit = $this->container->get('aurora.git');
        $root       = $this->container->getParameter('root');

        if (!$combine) {
            foreach ($assets as $asset) {
                $asset = trim($asset);
                if (strlen($asset) > 3) {
                    if (!preg_match('/http:|https:/', $asset)) {
                        $asset = $asset . '?v=' . (('dev' === $this->container->getParameter('kernel.environment')) ? uniqid() : $serviceGit->getHash());
                    }
                    //echo "\n\t" . '<link rel="preload" href="'. $asset .'" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
                    //<noscript><link rel="stylesheet" href="styles.css"></noscript>
                    echo "\n\t" . '<link type="text/css" rel="stylesheet" href="' . $asset . '" />';
                }
            }

            // Combine multiple assets into one single file
        } else {
            $root          = $this->container->getParameter('aurora.root');
            $cacheFileName = sha1(json_encode($assets) . $serviceGit->getHash()) . '.css';
            $cacheFilePath = "{$root}/web/static/compiled/{$cacheFileName}";

            /**
             * On dev, is we simulate the prod environment, remove the cached css on every request
             */
            if ('dev' === $this->container->getParameter('kernel.environment') && file_exists($cacheFilePath)) {
                unlink($cacheFilePath);
            }

            // Remove comments
            $regex = [
                "`^([\t\s]+)`ism"                       => '',
                "`^\/\*(.+?)\*\/`ism"                   => "",
                "`(\A|[\n;]+)/\*.+?\*/`s"               => "$1",
                "`(\A|[;\s]+)//.+\R`"                   => "$1\n",
                "`(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+`ism" => "\n"
            ];

            // If cache file does't exists
            if (!file_exists($cacheFilePath)) {
                $outputHeader = '';
                $output       = '';
                $minifier     = new Minify\CSS();
                foreach ($assets as $asset) {
                    $asset = trim($asset);
                    if (strlen($asset) > 3) {
                        $basename = basename($asset);
                        $baseDir  = str_ireplace($basename, '', $asset);
                        $css      = '';
                        if (preg_match('/http:|https:/', $asset)) {
                            $outputHeader .= ';@import url("' . $asset . '");';
                        } else {
                            $css = "/*! {$basename} ^ {$asset} ^ {$baseDir} */" . file_get_contents($root . '/public/' . $asset);

                            preg_match_all("/url\((?!['\"]?(?:data|http):)['\"]?([^'\"\)]*)['\"]?\)/", $css, $matches);
                            foreach ($matches[0] as $urlToImport) {
                                $urlToImport2 = str_ireplace('url(', "url({$baseDir}", $urlToImport);
                                $css          = str_replace($urlToImport, $urlToImport2, $css);
                            }
                        }

                        // Remove comments
                        $css = preg_replace(array_keys($regex), $regex, $css);

                        if ($minify) {
                            $minifier->add($css);
                        } else {
                            $output .= $css;
                        }
                    }
                }

                $output = $outputHeader . $output;

                //$baseurl = $Request->getScheme() . '://' . $Request->getHttpHost() . $Request->getBasePath();
                //$output = str_replace('../webfonts/', "{$baseurl}/static/fontawesome-free/5.4.2/webfonts/", $output);

                $cacheContent = ($minify ? $minifier->minify() : $output);

                //$cacheContent = str_replace('url(/static/', 'url(' . $baseurl . '/static/', $cacheContent);
                file_put_contents("{$root}/public/static/compiled/{$cacheFileName}", $cacheContent);
            }

            echo '<link type="text/css" rel="stylesheet" href="/static/compiled/' . $cacheFileName . '" />';
        }
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

                if ($combineAndMinify && ($onDev || !file_exists($combineAndMinifyOutputAbsPath))) {
                    if (!preg_match('/http:|https:|ftp:/', $assetWebPath)) {
                        $combineAndMinifyOutputContent .= ('css' == $assetType
                            ? $serviceSanitizer->cssMinify(file_get_contents($assetAbsPath), $assetWebPath)
                            : \JShrink\Minifier::minify(file_get_contents($assetAbsPath), ['flaggedComments' => false]) . ';'
                        );
                    } else {
                        if ('css' == $assetType) {
                            $combineAndMinifyOutputContentHead .= '@import url("' . $assetWebPath . '");';
                        } else {
                            trigger_error('External JS files not supported.');
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