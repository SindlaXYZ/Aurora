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

class ComposerCommand extends Command
{
    /**
     * The name of the command (the part after "bin/console")
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:composer
     *
     * @var string
     */
    protected static $defaultName = 'aurora:composer';

    /** @var InputInterface input */
    protected $input;

    /** @var OutputInterface output */
    protected $output;

    /** @var SymfonyStyle io */
    protected $io;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Composer commands')
            ->setHelp('Composer update command')
            // Mandatory
            ->addOption('action', NULL, InputOption::VALUE_REQUIRED);
    }

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container     = $container;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
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
        /** @var InputInterface input */
        $this->input = $input;

        /** @var OutputInterface output */
        $this->output = $output;

        /** @var SymfonyStyle io */
        $this->io = new SymfonyStyle($this->input, $this->output);

        $this->io->success(sprintf('%s Start running %s', $this->p(), $this->getName()));

        $action = trim($input->getOption('action'));

        if (empty($action)) {
            return $this->io->warning("Invalid action: not specified.");
        }

        if ('_' == substr($action, 0, 1)) {
            return $this->io->warning("Invalid action {$action}()");
        }

        if (method_exists($this, $action)) {
            $this->io->comment("[AURORA] Start to execute {$action}()");
            $this->$action();
            $this->io->newLine();
            $this->io->success('[AURORA] All commands were successfully run (post update).');
        } else {
            return $this->outputWithTime("Invalid action {$action}()");
        }

        return Command::SUCCESS;
    }

    /**
     * clear; php bin/console aurora:composer --action=postInstall
     */
    private function postInstall()
    {
        // GeoIP2Country
        $this->_updateGeoIP2('Country');

        // GeoIP2City
        $this->_updateGeoIP2('City');

        $this->_cleanUpAndChecks(__FUNCTION__);
    }

    /**
     * clear; php bin/console aurora:composer --action=postUpdate
     */
    private function postUpdate()
    {
        // PHPUnit
        $this->_updatePHPUnit();

        // GeoIP2Country
        $this->_updateGeoIP2('Country');

        // GeoIP2City
        $this->_updateGeoIP2('City');

        $this->_cleanUpAndChecks(__FUNCTION__);

        if (FALSE) {
            // Copy /Static/js
            $this->io->newLine();
            $this->output->writeln(sprintf('%s Copy the <info>/Static/js/*</info> to <info>/web/static/js/aurora/</info>', $this->p()));

            /** @var IO $IOService */
            $IOService = $this->container->get('aurora.io');
            $IOService->recursiveCreateDirectory($this->kernelRootDir . '/web/static/aurora/js/');

            copy(realpath(dirname(__FILE__)) . '/../Static/js/f.adblock.js', $this->kernelRootDir . '/web/static/aurora/js/f.adblock.js');

            $this->io->comment(sprintf('%s ... done;', $this->p()));
        }
    }

    public function _updatePHPUnit()
    {
        $this->io->comment(sprintf('%s Updating the <info>PHPUnit</info> ...', $this->p()));

        $phpUnitFile = $this->kernelRootDir . '/phpunit.phar';

        // If file is not older than X hours
        if (file_exists($phpUnitFile) && (time() - filemtime($phpUnitFile)) < 60 * 60 * 24) {
            $this->io->comment(sprintf('%s ... skip updating (PHPUnit is too new)', $this->p()));
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

        $this->io->comment(sprintf('%s ... done;', $this->p()));
    }

    /**
     * @param string $type Country|City
     * @throws \Exception
     */
    private function _updateGeoIP2(string $type)
    {
        if (!in_array($type, ['Country', 'City'])) {
            $this->io->error("[AURORA] _updateGeoIP2() invalid type!");
            return;
        }

        $this->io->comment(sprintf('%s Updating the <info>Maxmind GeoIP2/GeoIP2' . $type . '</info> ...', $this->p()));

        $tempDir           = $this->container->getParameter('aurora.tmp') . '/' . microtime(TRUE);
        $maxmindDir        = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2';
        $maxmindLicenseKey = trim($this->container->getParameter('aurora.maxmind.license_key'));
        $destinationFile   = "{$maxmindDir}/GeoLite2{$type}.mmdb";

        if (empty($maxmindLicenseKey)) {
            $this->io->error("[AURORA] Maxmind license key is not set.");
            $this->io->error("[AURORA] Check `MAXMIND_LICENSE_KEY=` inside .env file.");
            return;
        }

        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, TRUE)) {
            throw new \RuntimeException("[AURORA] Cannot create temporary dir `{$tempDir}`");
        }

        if (!is_dir($maxmindDir)) {
            try {
                mkdir($maxmindDir, 0777, TRUE);
            } catch (\Exception $e) {
                return $this->io->error("[AURORA] Cannot create maxmind dir `{$maxmindDir}`");
            }
        }

        // If file is not older than X hours
        if (file_exists($destinationFile) && time() - filemtime($destinationFile) < 60 * 60 * 24) {
            $this->io->comment(sprintf('%s ... skip updating (GeoIP2/GeoLite2%s is too new)', $this->p(), $type));
            return;
        }

        try {
            $tarGz = fopen("https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-{$type}&license_key={$maxmindLicenseKey}&suffix=tar.gz", 'r');
        } catch (\Exception $e) {
            return $this->io->error("[AURORA] Cannot download .tar.gz file from geolite.maxmind.com.");
        }

        if (!file_put_contents("{$tempDir}/GeoLite2-{$type}.tar.gz", $tarGz)) {
            return $this->io->error("[AURORA] Cannot write .tar.gz file on disk.");
        }

        // Decompress from gz
        $pharError = FALSE;
        try {
            $PharData = new \PharData("{$tempDir}/GeoLite2-{$type}.tar.gz");
        } catch (\UnexpectedValueException $e) {
            $pharError = TRUE;
            throw new \Exception('[AURORA] Could not read .tar.gz file.');
        } catch (\BadMethodCallException $e) {
            $pharError = TRUE;
            throw new \Exception('[AURORA] Something goes wrong with the .tar.gz file.');
        } finally {
            if ($pharError) {
                return $this->_updateGeoIP2($type);
            }
        }

        $PharData->decompress();

        // unarchive from the tar
        $phar = new \PharData(glob($tempDir . "/*.tar")[0]);
        $phar->extractTo($tempDir);

        if (!copy(glob($tempDir . "/*/*.mmdb")[0], $destinationFile)) {
            throw new \RuntimeException("[AURORA] Cannot copy .mmdb file.");
        }

        $this->io->comment(sprintf('%s ... done;', $this->p()));
    }

    private function _cleanUpAndChecks(string $functionName)
    {
        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // Static compiled JS & CSS files

        $auroraRootDir = $this->container->getParameter('aurora.root'); // %kernel.project_dir%
        $auroraTmpDir  = $this->container->getParameter('aurora.tmp'); // %kernel.project_dir%/var/tmp

        // Can be: /tmp/domain.tld/var/cache/dev/aurora/
        $auroraCacheDirs = [
            preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled')),
            preg_replace('~//+~', '/', ($auroraRootDir . '/public/static/compiled'))
        ];

        foreach ($auroraCacheDirs as $auroraCacheDir) {
            if (!is_dir($auroraCacheDir) && !mkdir($auroraCacheDir, 0777, TRUE)) {
                throw new \RuntimeException("[AURORA] Cannot create cache dir `{$auroraCacheDir}`");
            } else {
                /** @var IO $IOService */
                $IOService = $this->container->get('aurora.io');

                foreach (glob($auroraCacheDir . '/', GLOB_ONLYDIR) as $directory) {
                    $IOService->recursiveDelete($directory, FALSE);
                }
            }
        }

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // _clearTmpDir : Clear /var/tmp/*

        if ('postUpdate' == $functionName) {

            $this->io->comment(sprintf('%s Clearing the <info>/var/tmp/*</info> ...', $this->p()));

            /** @var IO $IOService */
            $IOService = $this->container->get('aurora.io');
            foreach (glob($this->container->getParameter('aurora.tmp') . '/', GLOB_ONLYDIR) as $directory) {
                $IOService->recursiveDelete($directory, FALSE);
            }

            $this->io->comment(sprintf('%s ... done;', $this->p()));
        }
    }
}