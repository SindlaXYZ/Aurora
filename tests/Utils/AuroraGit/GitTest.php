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
}

