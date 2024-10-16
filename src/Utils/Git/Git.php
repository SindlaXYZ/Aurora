<?php

namespace Sindla\Bundle\AuroraBundle\Utils\Git;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\DependencyInjection\Container;

/**
 * Debug: php bin/console debug:container aurora.git
 *
 * Class Git
 *
 * @package AuroraBundle\Utils
 */
class Git
{
    protected $container;

    public function __construct(Container $Container)
    {
        $this->container = $Container;
    }

    public function getBranch()
    {
        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__), function (ItemInterface $item) {
            $root = $this->container->getParameter('aurora.root');
            $this->container->getParameter('aurora.root');

            if (is_dir($root . '/.git/')) {
                $stringFromFile = file($root . '/.git/HEAD', FILE_USE_INCLUDE_PATH);
                $firstLine      = $stringFromFile[0];          //get the string from the array
                $explodedString = explode("/", $firstLine, 3); //seperate out by the "/" in the string
                $branchName     = $explodedString[2];          //get the one that is always the branch name

                return trim($branchName);
            } else {
                $item->expiresAfter(10);
                return 'NOT-A-GIT-REPO';
            }
        });
    }

    public function gitLatestTag(): ?string
    {
        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__), function (ItemInterface $item) {

            $root = $this->container->getParameter('aurora.root');

            if (is_dir($root . '/.git/refs/tags/')) {
                if ($tags = glob($root . '/.git/refs/tags/*')) {
                    natsort($tags);
                    $reverse = array_reverse($tags);
                    if ($reverse[0] ?? null) {
                        return basename($reverse[0]);
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }

            } else {
                $item->expiresAfter(10);
                return null;
            }
        });
    }

    public function gitLatestTagHash(): ?string
    {
        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__), function (ItemInterface $item) {

            $root = $this->container->getParameter('aurora.root');

            if (is_dir($root . '/.git/refs/tags/')) {
                if ($tags = glob($root . '/.git/refs/tags/*')) {
                    natsort($tags);
                    $reverse = array_reverse($tags);
                    if ($reverse[0] ?? null) {
                        return trim(file_get_contents($reverse[0]));
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }

            } else {
                $item->expiresAfter(10);
                return null;
            }
        });
    }

    public function getHash(?string $branch = null)
    {
        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__), function (ItemInterface $item) use ($branch) {
            if (!$branch) {
                if (!$branch = $this->getBranch()) {
                    $branch = 'main';
                }
            }

            $root = $this->container->getParameter('aurora.root');

            if (is_dir($root . '/.git/')) {
                if (file_exists($root . '/.git/refs/heads/' . $branch)) {
                    return trim(file_get_contents($root . '/.git/refs/heads/' . $branch));
                } else if (file_exists($root . '/.git/refs/remotes/' . $branch)) {
                    return trim(file_get_contents($root . '/.git/refs/remotes/' . $branch));
                } else if ('dev' != $branch && file_exists($root . '/.git/refs/heads/dev')) {
                    return trim(file_get_contents($root . '/.git/refs/heads/dev'));
                } else {
                    return 'N/A';
                }
            } else {
                $item->expiresAfter(10);
                return 'N/A';
            }
        });
    }

    public function getDate(?string $branch = null): ?string
    {
        $cache = new ApcuAdapter('', ('prod' == $this->container->getParameter('kernel.environment') ? (60 * 60 * 24) : 1));

        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__ . __LINE__), function (ItemInterface $item) use ($branch) {

            if (!$branch) {
                if (!$branch = $this->getBranch()) {
                    $branch = 'main';
                }
            }

            $root = $this->container->getParameter('aurora.root');

            if (is_dir($root . '/.git/')) {
                if (file_exists($root . '/.git/logs/refs/heads/' . $branch)) {
                    $handle = fopen($root . '/.git/logs/refs/heads/' . $branch, 'r');
                } else if (file_exists($root . '/.git/logs/HEAD')) {
                    $handle = fopen($root . '/.git/logs/HEAD', 'r');
                } else {
                    $item->expiresAfter(10);
                    return 'NOT-A-GIT-REPO';
                }

                $lastLine = '';
                if ($handle) {
                    while (($line = fgets($handle)) !== false) {
                        if (!empty(trim($line))) {
                            $lastLine = $line;
                        }
                    }
                    fclose($handle);
                }

                preg_match_all('/>\s((?<!\d)\d{10}(?!\d))\s/', $lastLine, $matches);
                $date = ((is_array($matches) && isset($matches[0]) && isset($matches[1][0])) ? date('Y-m-d H:i:s', $matches[1][0]) : 'N/A');

                return $date;
            } else {
                $item->expiresAfter(10);
                return 'NOT-A-GIT-REPO';
            }
        });
    }

    public function getTag(): ?string
    {

    }
}
