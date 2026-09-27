<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraGit;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class GitTest extends TestCase
{
    private function createParameterBag(string $root): ParameterBag
    {
        return new ParameterBag([
            'kernel.environment' => 'dev',
            'aurora.root'        => $root,
        ]);
    }

    public function testGetTagReturnsLatestTag(): void
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . uniqid();
        mkdir($root . '/.git/refs/tags', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/refs/tags/v1', 'hash1');
        file_put_contents($root . '/.git/refs/tags/v2', 'hash2');

        $git = new AuroraGit($this->createParameterBag($root));

        $this->assertSame('v2', $git->getTag());
        $this->assertSame('hash2', $git->gitLatestTagHash());
    }

    public function testFallbackWhenNotGitRepo(): void
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . uniqid();
        mkdir($root, 0777, true);

        $git = new AuroraGit($this->createParameterBag($root));

        $this->assertSame('NOT-A-GIT-REPO', $git->getBranch());
        $this->assertNull($git->getTag());
    }

    public function testDetachedHead(): void
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . uniqid();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', str_repeat('a1', 20) . "\n");

        $git = new AuroraGit($this->createParameterBag($root));

        // e.g. a deploy of a tag: reading the branch name was an "Undefined array key" warning (a 500 in debug)
        $this->assertSame('HEAD', $git->getBranch());
        $this->assertSame(str_repeat('a1', 20), $git->getHash());
    }

    public function testHashOfAPackedReference(): void
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . uniqid();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/feature/pwa\n");
        file_put_contents($root . '/.git/packed-refs', implode("\n", [
            '# pack-refs with: peeled fully-peeled sorted',
            str_repeat('b2', 20) . ' refs/heads/feature/pwa',
            str_repeat('c3', 20) . ' refs/heads/main',
            str_repeat('d4', 20) . ' refs/tags/v1.0',
            '^' . str_repeat('e5', 20),
        ]) . "\n");

        $git = new AuroraGit($this->createParameterBag($root));

        // "git gc" packs the references: the hash used to be "N/A", so the PWA version never changed
        $this->assertSame('feature/pwa', $git->getBranch());
        $this->assertSame(str_repeat('b2', 20), $git->getHash());
        $this->assertSame(str_repeat('c3', 20), $git->getHash('main'));
    }

    public function testALooseReferenceWinsOverAPackedOne(): void
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . uniqid();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/refs/heads/main', str_repeat('f6', 20) . "\n");
        file_put_contents($root . '/.git/packed-refs', str_repeat('c3', 20) . " refs/heads/main\n");

        $this->assertSame(str_repeat('f6', 20), new AuroraGit($this->createParameterBag($root))->getHash());
    }

    public function testTheCacheKeysDependOnTheProjectAndOnTheBranch(): void
    {
        $cacheKey = new \ReflectionMethod(AuroraGit::class, 'cacheKey');
        $first    = new AuroraGit($this->createParameterBag('/srv/first'));
        $second   = new AuroraGit($this->createParameterBag('/srv/second'));

        // APCu is shared by the applications of the server: they used to read the hash of each other
        $this->assertNotSame($cacheKey->invoke($first, 'getHash'), $cacheKey->invoke($second, 'getHash'));
        $this->assertNotSame($cacheKey->invoke($first, 'getHash', 'main'), $cacheKey->invoke($first, 'getHash', 'dev'));
    }
}
