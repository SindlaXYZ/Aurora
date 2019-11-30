<?php

namespace Sindla\Bundle\AuroraBundle\Command;

// Symfony
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

// Custom
use Sindla\Bundle\AuroraBundle\Service\IO\IO;

class ComposerUpdateCommand extends Command
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
    protected static $defaultName = 'aurora:composer.update';

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Composer commands')
            ->setHelp('Composer update command');
    }

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container      = $container;
        $this->kernelRootDir  = $this->container->getParameter('kernel.project_dir');
    }

    private function p()
    {
        return '[AURORA]';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $io = new SymfonyStyle($this->input, $this->output);
        $io->newLine();

        $io->comment(sprintf('%s Start running %s', $this->p(), $this->getName()));

        // PHPUnit
        $io->newLine();
        $this->output->writeln(sprintf('%s Updating the <info>PHPUnit</info> ...', $this->p()));
        $this->updatePHPUnit();
        $this->output->writeln(sprintf('%s ... done;', $this->p()));

        // GeoIP2
        $io->newLine();
        $this->output->writeln(sprintf('%s Updating the <info>Maxmind GeoIP2/GeoLite2Country</info> ...', $this->p()));
        $this->updateGeoIP2Contry();
        $this->output->writeln(sprintf('%s ... done;', $this->p()));

        // /var/tmp/*
        $io->newLine();
        $this->output->writeln(sprintf('%s Clearing the <info>/var/tmp/*</info> ...', $this->p()));
        $this->clearTmpDir();
        $this->output->writeln(sprintf('%s ... done;', $this->p()));

        if (false) {
            // Copy /Static/js
            $io->newLine();
            $this->output->writeln(sprintf('%s Copy the <info>/Static/js/*</info> to <info>/web/static/js/aurora/</info>', $this->p()));

            /** @var IO $IOService */
            $IOService = $this->container->get('aurora.io');
            $IOService->recursiveCreateDirectory($this->kernelRootDir . '/web/static/aurora/js/');

            copy(realpath(dirname(__FILE__)) . '/../Static/js/f.adblock.js', $this->kernelRootDir . '/web/static/aurora/js/f.adblock.js');
            $this->output->writeln(sprintf('%s ... done;', $this->p()));
        }

        $io->newLine();
        $io->success('[AURORA] All commands were successfully run.');
    }

    public function updatePHPUnit()
    {
        $phpUnitFile = $this->kernelRootDir . '/phpunit.phar';

        // If file is not older than X hours
        if (file_exists($phpUnitFile) && (time() - filemtime($phpUnitFile)) < 60 * 60 * 24) {
            $this->output->writeln(sprintf('%s Skip updating the <info>PHPUnit</info>. The file is too new.', $this->p()));
            return;
        }

        // Check https://phar.phpunit.de/
        if (!$phar = fopen('https://phar.phpunit.de/phpunit.phar', 'r')) {
            throw new \RuntimeException("[AURORA] Cannot download .phar file from phar.phpunit.de.");
        }

        try {
            file_put_contents($phpUnitFile, $phar);
        } catch (\Exception $e) {
            throw new \RuntimeException("[AURORA] Cannot write phpunit.phar file on disk.");
        }
    }

    public function updateGeoIP2Contry()
    {
        $tempDir         = $this->container->getParameter('aurora.tmp') . '/' . microtime(true);
        $maxmindDir      = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2';
        $destinationFile = $maxmindDir . '/GeoLite2Country.mmdb';

        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true)) {
            throw new \RuntimeException("[AURORA] Cannot create temporary dir `{$tempDir}`");
        }

        if (!is_dir($maxmindDir)) {
            try {
                mkdir($maxmindDir, 0777, true);
            } catch (\Exception $e) {
                throw new \RuntimeException("[AURORA] Cannot create maxmind dir `{$maxmindDir}`");
            }
        }

        // If file is not older than X hours
        if (file_exists($destinationFile) && time() - filemtime($destinationFile) < 60 * 60 * 24) {
            $this->output->writeln(sprintf('%s Skip updating the <info>Maxmind GeoIP2/GeoLite2Country</info>. The file is too new.', $this->p()));
            return;
        }

        // Check http://dev.maxmind.com/geoip/geoip2/geolite2/
        if (!$tarGz = fopen("http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz", 'r')) {
            throw new \RuntimeException("[AURORA] Cannot download .tar.gz file from geolite.maxmind.com.");
        }

        if (!file_put_contents($tempDir . '/GeoLite2-Country.tar.gz', $tarGz)) {
            throw new \RuntimeException("[AURORA] Cannot write .tar.gz file on disk.");
        }

        // Decompress from gz
        $pharError = false;
        try {
            $PharData = new \PharData($tempDir . '/GeoLite2-Country.tar.gz');
        } catch (\UnexpectedValueException $e) {
            $pharError = true;
            throw new \Exception('[AURORA] Could not read .tar.gz file.');
        } catch (\BadMethodCallException $e) {
            $pharError = true;
            throw new \Exception('[AURORA] Something goes wrong with the .tar.gz file.');
        } finally {
            if ($pharError) {
                return $this->updateGeoIP2Contry();
            }
        }

        $PharData->decompress();

        // unarchive from the tar
        $phar = new \PharData(glob($tempDir . "/*.tar")[0]);
        $phar->extractTo($tempDir);

        if (!copy(glob($tempDir . "/*/*.mmdb")[0], $destinationFile)) {
            throw new \RuntimeException("[AURORA] Cannot copy .mmdb file.");
        }
    }

    public function clearTmpDir()
    {
        /** @var IO $IOService */
        $IOService = $this->container->get('aurora.io');
        foreach (glob($this->container->getParameter('aurora.tmp') . '/', GLOB_ONLYDIR) as $directory) {
            $IOService->recursiveDelete($directory, false);
        }
    }
}