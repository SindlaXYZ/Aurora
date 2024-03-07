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
use Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge\AuroraPHPUnitCodeCoverageBadge;

#[AsCommand(
    name       : 'aurora:php-unit-code-coverage-badge',
    description: 'Generate PHPUnit code coverage badge',
    aliases    : ['aurora:puccb']
)]
final class PHPUnitCodeCoverageBadgeCommand extends CommandMiddleware
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
            ->setHelp('Aurora PHPUnit code coverage badge command')
            // Mandatory
            ->addOption('action', null, InputOption::VALUE_REQUIRED)
            // Optional
            ->addOption('cloverXMLFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputSVGFilePath', null, InputOption::VALUE_OPTIONAL);
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
     *      clear; /usr/bin/php bin/console aurora:php-unit-code-coverage-badge --action=test
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
     *      clear; /usr/bin/php bin/console aurora:php-unit-code-coverage-badge --action=generate --cloverXMLFilePath=build/logs/clover.xml --outputSVGFilePath=.github/badges/coverage.svg
     */
    protected function generate(): int
    {
        if (
            !($cloverXMLFilePath = $this->input->getOption('cloverXMLFilePath') ?? null)
            || !($outputSVGFilePath = $this->input->getOption('outputSVGFilePath') ?? null)
        ) {
            throw new \Exception('Missing required options.');
        }

        $cloverXMLFilePath = $this->container->getParameter('kernel.project_dir') . '/' . $cloverXMLFilePath;
        $outputSVGFilePath = $this->container->getParameter('kernel.project_dir') . '/' . $outputSVGFilePath;

        (new AuroraPHPUnitCodeCoverageBadge())->generate($cloverXMLFilePath, $outputSVGFilePath);

        return self::SUCCESS;
    }
}
