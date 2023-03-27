<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'aurora:test',
    description: 'Aurora test command',
    aliases: ['aurora:test']
)]
class TestCommand extends Command
{
    /**
     * The name of the command (the part after "bin/console")
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:test
     *
     * @var string
     */
    protected static $defaultName = 'aurora:test';

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to test Aurora Command service');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input->comment('It works!');

        return Command::SUCCESS;
    }
}
