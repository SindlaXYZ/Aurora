<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'aurora:test',
    description: 'Aurora test command',
    aliases: ['aurora:test']
)]
class TestCommand extends Command
{
    protected SymfonyStyle $io;

    protected function configure(): void
    {
        $this->setHelp('This command allows you to test Aurora Command service');
    }

    /**
     * {@inheritdoc}
     *
     * Usage: clear; php bin/console aurora:test
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var SymfonyStyle io */
        $this->io = new SymfonyStyle($input, $output);
        $this->io->success('It works!');

        return Command::SUCCESS;
    }
}
