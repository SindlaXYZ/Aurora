<?php

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraGit;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Debug: php bin/console debug:container aurora.git
 */
class AuroraGit
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    private function createCache(): AdapterInterface
    {
        $lifetime = ('prod' == $this->parameterBag->get('kernel.environment') ? (60 * 60 * 24) : 1);

        if (ApcuAdapter::isSupported()) {
            return new ApcuAdapter('', $lifetime);
        }

        return new ArrayAdapter($lifetime);
    }

    /**
     * The key depends on the project and on the arguments: APCu is shared by the applications of the server (they used to read
     * the hash of each other, and the one of the previous release after a deploy), and getHash("dev") returned the hash of the
     * first branch asked for
     */
    private function cacheKey(string $method, ?string $argument = null): string
    {
        return sha1(__CLASS__ . '::' . $method . '|' . $this->parameterBag->get('aurora.root') . '|' . $argument);
    }

    /**
     * .git/HEAD: "ref: refs/heads/<branch>", or the hash of the commit when the HEAD is detached (e.g. a deploy of a tag)
     */
    private function readHead(string $root): ?string
    {
        $head = is_file($root . '/.git/HEAD') ? file_get_contents($root . '/.git/HEAD') : false;

        return false === $head ? null : trim($head);
    }

    /**
     * The hash of a loose or of a packed reference (.git/packed-refs, written by "git gc" and "git pack-refs")
     */
    private function readReference(string $root, string $reference): ?string
    {
        if (is_file($root . '/.git/' . $reference)) {
            return trim((string)file_get_contents($root . '/.git/' . $reference));
        }

        if (is_file($root . '/.git/packed-refs')) {
            foreach (file($root . '/.git/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                // "<hash> <reference>" ("#" lines are comments, "^<hash>" lines are peeled tags)
                if (1 === preg_match('/^([0-9a-f]{40,64}) (.+)$/', $line, $matches) && $matches[2] === $reference) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    public function getBranch()
    {
        $cache = $this->createCache();

        return $cache->get($this->cacheKey(__FUNCTION__), function (ItemInterface $item) {
            $root = $this->parameterBag->get('aurora.root');

            if (is_dir($root . '/.git/')) {
                $head = (string)$this->readHead($root);

                // A detached HEAD has no branch: "HEAD", like "git rev-parse --abbrev-ref HEAD" (reading the branch name from the
                // hash was an "Undefined array key" warning, a 500 in debug)
                return str_starts_with($head, 'ref: refs/heads/') ? substr($head, strlen('ref: refs/heads/')) : 'HEAD';
            } else {
                $item->expiresAfter(10);
                return 'NOT-A-GIT-REPO';
            }
        });
    }

    public function gitLatestTag(): ?string
    {
        $cache = $this->createCache();

        return $cache->get($this->cacheKey(__FUNCTION__), function (ItemInterface $item) {

            $root = $this->parameterBag->get('aurora.root');

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
        $cache = $this->createCache();

        return $cache->get($this->cacheKey(__FUNCTION__), function (ItemInterface $item) {

            $root = $this->parameterBag->get('aurora.root');

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
        $cache = $this->createCache();

        return $cache->get($this->cacheKey(__FUNCTION__, $branch), function (ItemInterface $item) use ($branch) {
            $root = $this->parameterBag->get('aurora.root');

            if (is_dir($root . '/.git/')) {
                if (!$branch || 'HEAD' === $branch) {
                    $head = (string)$this->readHead($root);

                    // Detached HEAD: .git/HEAD is the hash
                    if (1 === preg_match('/^[0-9a-f]{40,64}$/', $head)) {
                        return $head;
                    }

                    $branch = str_starts_with($head, 'ref: refs/heads/') ? substr($head, strlen('ref: refs/heads/')) : 'main';
                }

                // The references packed by "git gc" used to give "N/A": a PWA version (and "?v=") that never changes
                return $this->readReference($root, 'refs/heads/' . $branch)
                    ?? $this->readReference($root, 'refs/remotes/' . $branch)
                    ?? ('dev' != $branch ? $this->readReference($root, 'refs/heads/dev') : null)
                    ?? 'N/A';
            } else {
                $item->expiresAfter(10);
                return 'N/A';
            }
        });
    }

    public function getDate(?string $branch = null): ?string
    {
        $cache = $this->createCache();

        return $cache->get($this->cacheKey(__FUNCTION__, $branch), function (ItemInterface $item) use ($branch) {

            if (!$branch) {
                if (!$branch = $this->getBranch()) {
                    $branch = 'main';
                }
            }

            $root = $this->parameterBag->get('aurora.root');

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

                return isset($matches[1][0]) ? date('Y-m-d H:i:s', (int)$matches[1][0]) : 'N/A';
            } else {
                $item->expiresAfter(10);
                return 'NOT-A-GIT-REPO';
            }
        });
    }

    public function getTag(): ?string
    {
        return $this->gitLatestTag();
    }
}
