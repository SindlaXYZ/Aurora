<?php

namespace Sindla\Bundle\BorealisBundle\Command;

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
     *      clear; php bin/console borealis:test
     *
     * @var string
     */
    protected static $defaultName = 'borealis:test';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Test command')
            ->setHelp('This command allows you to test Borealis Command service');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('It works!');
    }
}