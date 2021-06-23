<?php

namespace Sindla\Bundle\AuroraBundle\Command;

// Symfony
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    protected static string $defaultName = 'aurora:test';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Test command')
            ->setHelp('This command allows you to test Aurora Command service');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->comment('It works!');

        return Command::SUCCESS;
    }
}
