<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraGit;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraGit\AuroraGit;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

class GitTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $roots = [];

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->roots);
    }

    private function createParameterBag(string $root): ParameterBag
    {
        return new ParameterBag([
            'kernel.environment' => 'dev',
            'aurora.root'        => $root,
        ]);
    }

    public function testGetTagReturnsLatestTag(): void
    {
        $root = $this->createRoot();
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
        $root = $this->createRoot();

        $git = new AuroraGit($this->createParameterBag($root));

        $this->assertSame('NOT-A-GIT-REPO', $git->getBranch());
        $this->assertNull($git->getTag());
    }

    public function testDetachedHead(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', str_repeat('a1', 20) . "\n");

        $git = new AuroraGit($this->createParameterBag($root));

        // e.g. a deploy of a tag: reading the branch name was an "Undefined array key" warning (a 500 in debug)
        $this->assertSame('HEAD', $git->getBranch());
        $this->assertSame(str_repeat('a1', 20), $git->getHash());
    }

    public function testHashOfAPackedReference(): void
    {
        $root = $this->createRoot();
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
        $root = $this->createRoot();
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

    public function testTheLatestTagIsTheHighestVersion(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/refs/tags', 0777, true);
        foreach (['v1.2' => 'a1', 'v1.10' => 'b2', 'v1.9' => 'c3'] as $tag => $hash) {
            file_put_contents($root . '/.git/refs/tags/' . $tag, str_repeat($hash, 20) . "\n");
        }

        $git = new AuroraGit($this->createParameterBag($root));

        // Natural order: v1.10 is after v1.9
        $this->assertSame('v1.10', $git->gitLatestTag());
        $this->assertSame(str_repeat('b2', 20), $git->gitLatestTagHash());
    }

    public function testNoLatestTagWithoutTags(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/refs/tags', 0777, true);

        $git = new AuroraGit($this->createParameterBag($root));

        $this->assertNull($git->gitLatestTag());
        $this->assertNull($git->gitLatestTagHash());
        $this->assertNull(new AuroraGit($this->createParameterBag($this->createRoot()))->gitLatestTagHash());
    }

    public function testGetHashFallsBackToTheMainAndToTheDevBranches(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/refs/heads', 0777, true);
        mkdir($root . '/.git/refs/remotes/origin', 0777, true);
        file_put_contents($root . '/.git/refs/heads/main', str_repeat('a1', 20) . "\n");
        file_put_contents($root . '/.git/refs/heads/dev', str_repeat('b2', 20) . "\n");
        file_put_contents($root . '/.git/refs/remotes/origin/feature', str_repeat('c3', 20) . "\n");

        $git = new AuroraGit($this->createParameterBag($root));

        // Without .git/HEAD: the "main" branch
        $this->assertSame(str_repeat('a1', 20), $git->getHash());
        $this->assertSame(str_repeat('a1', 20), $git->getHash('HEAD'));
        // A remote branch
        $this->assertSame(str_repeat('c3', 20), $git->getHash('origin/feature'));
        // An unknown branch: the "dev" branch
        $this->assertSame(str_repeat('b2', 20), $git->getHash('unknown'));
    }

    public function testGetHashOfAnUnknownBranchWithoutADevBranch(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/refs/heads/main', str_repeat('a1', 20) . "\n");
        file_put_contents($root . '/.git/packed-refs', str_repeat('c3', 20) . " refs/heads/release\n");

        $git = new AuroraGit($this->createParameterBag($root));

        $this->assertSame('N/A', $git->getHash('unknown'));
        $this->assertSame('N/A', $git->getHash('dev'));
    }

    public function testGetDateReadsTheLastEntryOfTheReflog(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/logs/refs/heads', 0777, true);
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/.git/logs/refs/heads/main', implode("\n", [
            $this->reflogLine(1600000000, 'commit (initial): First'),
            $this->reflogLine(1700000000, 'commit: Second'),
            '',
            '',
        ]));
        file_put_contents($root . '/.git/logs/HEAD', $this->reflogLine(1500000000, 'checkout: moving from main to feature') . "\n");

        $git        = new AuroraGit($this->createParameterBag($root));
        $previousTz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            // The branch of .git/HEAD
            $this->assertSame('2023-11-14 22:13:20', $git->getDate());
            $this->assertSame('2023-11-14 22:13:20', $git->getDate('main'));
            // A branch without its own reflog: the reflog of HEAD
            $this->assertSame('2017-07-14 02:40:00', $git->getDate('feature'));
        } finally {
            date_default_timezone_set($previousTz);
        }
    }

    public function testGetDateWithoutADateInTheReflog(): void
    {
        $root = $this->createRoot();
        mkdir($root . '/.git/logs', 0777, true);
        file_put_contents($root . '/.git/logs/HEAD', "not a reflog entry\n");

        $this->assertSame('N/A', new AuroraGit($this->createParameterBag($root))->getDate('main'));
        $this->assertSame('NOT-A-GIT-REPO', new AuroraGit($this->createParameterBag($this->createRoot()))->getDate('main'));
    }

    public function testARealGitRepository(): void
    {
        exec('git --version 2>&1', $output, $exitCode);
        if (0 !== $exitCode) {
            $this->markTestSkipped('The git command is required for this test.');
        }

        $root = $this->createRoot();
        $git  = static function (string ...$arguments) use ($root): string {
            // A fixed date, without the configuration (identity, signing, hooks) of the machine
            $command = sprintf(
                'GIT_AUTHOR_DATE=%1$s GIT_COMMITTER_DATE=%1$s git -C %2$s -c user.name=Example -c user.email=user@example.com'
                . ' -c commit.gpgsign=false -c tag.gpgsign=false -c core.hooksPath=/dev/null %3$s 2>&1',
                escapeshellarg('1700000000 +0000'),
                escapeshellarg($root),
                implode(' ', array_map('escapeshellarg', $arguments))
            );
            exec($command, $output, $exitCode);

            if (0 !== $exitCode) {
                throw new \RuntimeException(sprintf('The command "%s" failed: %s', $command, implode("\n", $output)));
            }

            return trim(implode("\n", $output));
        };

        $git('-c', 'init.defaultBranch=main', '-c', 'init.defaultRefFormat=files', 'init', '--quiet');
        file_put_contents($root . '/README.md', "Example\n");
        $git('add', 'README.md');
        $git('commit', '--quiet', '-m', 'Initial commit');
        $git('tag', 'v1.0.0');
        $hash = $git('rev-parse', 'HEAD');

        $auroraGit  = new AuroraGit($this->createParameterBag($root));
        $previousTz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $this->assertSame('main', $auroraGit->getBranch());
            $this->assertSame($hash, $auroraGit->getHash());
            $this->assertSame('v1.0.0', $auroraGit->getTag());
            $this->assertSame($hash, $auroraGit->gitLatestTagHash());
            $this->assertSame('2023-11-14 22:13:20', $auroraGit->getDate());
        } finally {
            date_default_timezone_set($previousTz);
        }
    }

    private function createRoot(): string
    {
        $root = sys_get_temp_dir() . '/aurora_git_' . bin2hex(random_bytes(6));
        mkdir($root);
        $this->roots[] = $root;

        return $root;
    }

    private function reflogLine(int $timestamp, string $message): string
    {
        return sprintf("%s %s Example <user@example.com> %d +0000\t%s", str_repeat('0', 40), str_repeat('a1', 20), $timestamp, $message);
    }
}
