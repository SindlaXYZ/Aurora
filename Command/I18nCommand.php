<?php

namespace Sindla\Bundle\AuroraBundle\Command;

// Engine
use Sindla\Bundle\AuroraBundle\Service\IO\IO;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class I18nCommand extends ContainerAwareCommand
{
    /** @var  Container */
    private $container;

    private $input;

    private $output;

    /**
     * {@inheritDoc}
     *
     * clear; php bin/console i18n:update
     */
    protected function configure()
    {
        $this
            ->setName('aurora:i18n')
            ->setDescription('Generate setters and getter for an entity');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input     = $input;
        $this->output    = $output;
        $this->container = $this->getContainer();

        $this->update();
    }

    private function update()
    {
        $Yaml       = new Yaml();
        $bundleName = $this->container->getParameter('aurora')['bundle'];

        // Find, extract and dump translate keys from twig template
        $command    = $this->getApplication()->find('translation:update');
        $arguments  = array(
            'command' => 'translation:update',
            'locale' => 'en',
            'bundle' => $bundleName,
            '--dump-messages' => true,
            '--force' => true,
            '-q' => true,
        );
        $greetInput = new ArrayInput($arguments);
        $command->run($greetInput, $this->output);
        $dumpFile = $this->container->getParameter('root') . '/app/Resources/' . $bundleName . '/translations/messages.en.yml';
        $Yaml->parseFile($dumpFile);

        $defaultParsed = $Yaml->parseFile($dumpFile);

        foreach ($this->container->getParameter('aurora')['locales'] as $locale) {
            $locale        = strtolower($locale);
            $localeDirPath = $this->container->getParameter('aurora')['root'] . '/app/Resources/translations';
            $localeFile    = $localeDirPath . '/messages.' . $locale . '.yml';
            $localeContent = ((file_exists($localeFile) && is_file($localeFile) && is_readable($localeFile)) ? $Yaml->parseFile($localeFile) : []);
            file_put_contents($localeFile, trim($Yaml->dump($localeContent + $defaultParsed)));
        }

        /** @var IO $serviceIO */
        $serviceIO = $this->container->get('aurora.io');

        $bundlePath = $this->container->getParameter('root') . '/app/Resources/' . $bundleName;
        $serviceIO->recursiveDelete($bundlePath . '/translations/', true);

        if ($serviceIO->dirIsEmpty($bundlePath)) {
            $serviceIO->recursiveDelete($bundlePath, true);
        }

        return $this->output->writeln('DONE!');
    }
}