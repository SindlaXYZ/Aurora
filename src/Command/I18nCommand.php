<?php

namespace Sindla\Bundle\AuroraBundle\Command;


use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name       : 'aurora:i18n',
    description: 'Aurora i18n (internationalization) command',
    aliases    : ['aurora:internationalization']
)]
final class I18nCommand extends CommandMiddleware
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        protected ?ContainerInterface            $container,
        protected readonly ParameterBagInterface $parameterBag
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Aurora i18n command')
            // Mandatory
            ->addOption('action', null, InputOption::VALUE_REQUIRED)
            // Optional
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL);
    }

    /**
     * This optional method is the first one executed for a command after configure() and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
    }

    /**
     * This method is executed after initialize() and before execute(). Its purpose is to check if some of the options/arguments are missing and interactively ask the user for those values.
     *
     * This method is completely optional. If you are developing an internal console command, you probably should not implement this method because it requires quite a lot of work.
     * However, if the command is meant to be used by external users, this method is a nice way to fall back and prevent errors.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->try($input, $output, $this);
    }

    /**
     * Manual call:
     *      clear; /usr/bin/php bin/console aurora:i18n --action=test
     */
    protected function test(): int
    {
        $this->outputWithTime(sprintf("Command: %s", $this->commandName));
        $this->outputWithTime(sprintf("Application environment: %s", $this->container->getParameter('kernel.environment')));
        $this->outputWithTime(sprintf("Project directory: %s", $this->container->getParameter('kernel.project_dir')));

        return self::SUCCESS;
    }

    /**
     * Manual call:
     *      clear; /usr/bin/php bin/console aurora:i18n --action=dump
     *      clear; /usr/bin/php bin/console aurora:i18n --action=dump --locale=en
     */
    protected function dump(): int
    {
        $locale = $this->input->getOption('locale') ?? 'en';

        ($this->getApplication()->find('translation:extract'))->run(
            (new ArrayInput([
                '--force'  => true,
                '--format' => 'yaml',
                'locale'   => $locale
            ])),
            $this->output
        );

        return self::SUCCESS;
    }
}
