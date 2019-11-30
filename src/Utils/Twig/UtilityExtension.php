<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Twig;

// Symfony
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;

// Twig
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Environment;

// Minify
use MatthiasMullie\Minify;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\PWA\PWA;
use Symfony\Component\Yaml\Yaml;

class UtilityExtension extends AbstractExtension
{
    /**
     * @var Container
     */
    protected $container;

    protected $request;

    private $twig;

    /**
     * UtilityExtension constructor.
     *
     * @param $serviceContainer
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

    public function filterAge($date)
    {
        if (!$date instanceof \DateTime) {
            // turn $date into a valid \DateTime object or let return
            return null;
        }

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
            new TwigFunction('compressCss', [$this, 'compressCss']),
            new TwigFunction('compressJs', [$this, 'compressJs']),
            new TwigFunction('pwa', [$this, 'pwa']),
        ];
    }

    /**
     * Render and output twig template
     */
    public function pwa(Request $Request, bool $debug)
    {
        return $this->twig->display('@Aurora/favicon.html.twig', [
            'host'        => $Request->getHost(),
            'pwa'         => (bool)($Request->isSecure() || preg_match('/(.*\.localhost$|^localhost$)/i', $Request->getHost())),
            'theme_color' => $this->container->getParameter('aurora.pwa.theme_color'),
            'build'       => $this->getBuild(),
            'debug'       => $debug
        ]);
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

        if (!$combine) {
            foreach ($assets as $asset) {
                if (!preg_match('/http:|https:/', $asset)) {
                    $asset = $asset . '?v=' . (('dev' === $this->container->getParameter('kernel.environment')) ? uniqid() : $serviceGit->getHash());
                }
                echo "\n\t" . '<link type="text/css" rel="stylesheet" href="' . $asset . '" />';
            }

            // Combine multiple assets into one single file
        } else {
            $root          = $this->container->getParameter('aurora.root');
            $cacheFileName = $serviceGit->getHash() . '.css';
            $cacheFilePath = "{$root}/web/static/compiled/{$cacheFileName}";

            /**
             * On dev, is we simulate the prod environment, remove the cached css on every request
             */
            if ('dev' === $this->container->getParameter('kernel.environment') && file_exists($cacheFilePath)) {
                unlink($cacheFilePath);
            }

            // If cache file does't exists
            if (!file_exists($cacheFilePath)) {
                $outputHeader = '';
                $output       = '';
                $minifier     = new Minify\CSS();
                foreach ($assets as $asset) {
                    if (preg_match('/http:|https:/', $asset)) {
                        $outputHeader .= ';@import url("' . $asset . '");';
                    } else {
                        $css = ';' . file_get_contents($root . '/web/' . $asset);
                    }

                    if ($minify) {
                        $minifier->add($css);
                    } else {
                        $output .= $css;
                    }
                }

                $output = $outputHeader . $output;

                $baseurl = $Request->getScheme() . '://' . $Request->getHttpHost() . $Request->getBasePath();

                //$output = str_replace('../webfonts/', "{$baseurl}/static/fontawesome-free/5.4.2/webfonts/", $output);

                $cacheContent = ($minify ? $minifier->minify() : $output);
                $cacheContent = str_replace('url(/static/', 'url(' . $baseurl . '/static/', $cacheContent);
                file_put_contents("{$root}/web/static/compiled/{$cacheFileName}", $cacheContent);
            }

            echo '<link type="text/css" rel="stylesheet" href="/static/compiled/' . $cacheFileName . '" />';
        }
    }

    /**
     * Minify the JS into one single file
     * Save (if not exists) the minified file using the last GIT branch hash tag
     *
     * https://github.com/matthiasmullie/minify
     *
     * @param       $Request
     * @param mixed ...$assets
     */
    public function compressJs(Request $Request, $combine, $minify, ...$assets)
    {
        if (!$combine) {
            foreach ($assets as $asset) {
                echo "\n\t" . '<script src="' . $asset . '"></script>';
            }

        } else {
            $root       = $this->container->getParameter('aurora.root');
            $serviceGit = $this->container->get('aurora.git');
            //$serviceSanitizer = $this->Container->get('service.sanitizer');
            $cacheFileName = $serviceGit->getHash() . '.js';
            $cacheFilePath = "{$root}/web/static/compiled/{$cacheFileName}";

            /**
             * On dev, is we simulate the prod environment, remove the cached js on every request
             */
            if ('dev' === $this->container->getParameter('kernel.environment') && file_exists($cacheFilePath)) {
                unlink($cacheFilePath);
            }

            if (!file_exists($cacheFilePath)) {
                $minifier = new Minify\JS();
                foreach ($assets as $asset) {
                    //$js = $serviceSanitizer->minifyJS(file_get_contents($root . '/web/' . $asset)); // BUGGY
                    $js = file_get_contents($root . '/web/' . $asset);
                    $minifier->add($js);
                }

                file_put_contents("{$root}/web/static/compiled/{$cacheFileName}", $minifier->minify());
            }

            echo '<script src="/static/compiled/' . $cacheFileName . '"></script>';

        }
    }
}