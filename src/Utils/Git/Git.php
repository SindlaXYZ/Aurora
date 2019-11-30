<?php

namespace Sindla\Bundle\BorealisBundle\Utils\Git;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Debug: php bin/console debug:container borealis.git
 *
 * Class Git
 *
 * @package BorealisBundle\Utils
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
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) {
            $item->expiresAfter(60 * 30);

            $root = $this->container->getParameter('borealis.root');
            $this->container->getParameter('borealis.root');

            if (is_dir($root . '/.git/')) {
                $stringfromfile = file($root . '/.git/HEAD', FILE_USE_INCLUDE_PATH);
                $firstLine      = $stringfromfile[0];          //get the string from the array
                $explodedstring = explode("/", $firstLine, 3); //seperate out by the "/" in the string
                $branchname     = $explodedstring[2];          //get the one that is always the branch name

                return trim($branchname);
            } else {
                $item->expiresAfter(10);
                return 'NOT-A-GIT-REPO';
            }
        });
    }

    public function getHash(string $branch = 'master')
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) use ($branch) {
            $item->expiresAfter(60 * 30);

            $root = $this->container->getParameter('borealis.root');

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

    public function getDate(string $branch = 'master')
    {
        $cache = new FilesystemAdapter();
        return $cache->get(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__), function (ItemInterface $item) use ($branch) {
            $item->expiresAfter(60 * 30);

            $root = $this->container->getParameter('borealis.root');
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
}