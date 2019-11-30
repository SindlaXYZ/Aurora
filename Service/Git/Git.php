<?php

namespace Sindla\Bundle\AuroraBundle\Service\Git;

use Symfony\Component\DependencyInjection\Container;

/**
 * Debug: php bin/console debug:container aurora.git
 *
 * Class Git
 * @package AuroraBundle\Service
 */
class Git
{
    private $Container;

    public function __construct(Container $Container)
    {
        $this->Container = $Container;
    }

    public function getBranch()
    {
        $cacheAdapter = $this->Container->get('aurora_cacher');
        $cache        = $cacheAdapter->getItem(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__));

        // Cache not found
        if (!$cache->isHit() || empty($cache->get())) {
            $root           = $this->Container->getParameter('aurora')['root'];
            $stringfromfile = file($root . '/.git/HEAD', FILE_USE_INCLUDE_PATH);
            $firstLine      = $stringfromfile[0]; //get the string from the array
            $explodedstring = explode("/", $firstLine, 3); //seperate out by the "/" in the string
            $branchname     = $explodedstring[2]; //get the one that is always the branch name

            $cache->set(trim($branchname));
            $cache->expiresAfter(60 * 30); // seconds
            $cacheAdapter->save($cache);
        }

        return $cache->get();
    }

    public function getHash(string $branch = 'master')
    {
        $cacheAdapter = $this->Container->get('aurora_cacher');
        $cache        = $cacheAdapter->getItem(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__));
        $root         = $this->Container->getParameter('aurora')['root'];

        // Cache not found
        if (!$cache->isHit() || empty($cache->get())) {
            if (file_exists($root . '/.git/refs/heads/' . $branch)) {
                $cache->set(trim(file_get_contents($root . '/.git/refs/heads/' . $branch)));
            } else if (file_exists($root . '/.git/refs/remotes/' . $branch)) {
                $cache->set(trim(file_get_contents($root . '/.git/refs/remotes/' . $branch)));
            } else if ('dev' != $branch && file_exists($root . '/.git/refs/heads/dev')) {
                $cache->set(trim(file_get_contents($root . '/.git/refs/heads/dev')));
            } else {
                $cache->set('N/A');
            }

            $cache->expiresAfter(60 * 30); // seconds
            $cacheAdapter->save($cache);
        }

        return $cache->get();
    }

    public function getDate(string $branch = 'master')
    {
        $cacheAdapter = $this->Container->get('aurora_cacher');
        $cache        = $cacheAdapter->getItem(sha1(__NAMESPACE__ . __CLASS__ . __METHOD__));
        $root         = $this->Container->getParameter('aurora')['root'];

        // Cache not found
        if (!$cache->isHit() || empty($cache->get())) {
            if (file_exists($root . '/.git/logs/refs/heads/' . $branch)) {
                $handle = fopen($root . '/.git/logs/refs/heads/' . $branch, 'r');
            } else {
                $handle = fopen($root . '/.git/logs/HEAD', 'r');
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

            $cache->set($date);
            $cache->expiresAfter(60 * 30); // seconds
            $cacheAdapter->save($cache);
        }

        return $cache->get();
    }
}