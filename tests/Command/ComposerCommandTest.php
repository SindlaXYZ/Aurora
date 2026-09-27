<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\ComposerCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;

class ComposerCommandTest extends TestCase
{
    public function testMissingActionFailsGracefully(): void
    {
        $container = new Container();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $tester = new CommandTester(new ComposerCommand($container));

        // The "action" option defaults to null: trim(null) used to be a TypeError (strict_types)
        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Invalid action: not specified.', $tester->getDisplay());
    }
}
