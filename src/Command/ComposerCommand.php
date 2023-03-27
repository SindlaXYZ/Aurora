<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

// Vendor
use Sindla\Bundle\AuroraBundle\Utils\IO\IO;

#[AsCommand(
    name: 'aurora:composer',
    description: 'Composer update command',
    aliases: ['aurora:composer']
)]
class ComposerCommand extends Command
{
    /**
     * The name of the command (the part after "bin/console")
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:composer
     *
     * @var string|null
     */
    /** @var InputInterface input */
    protected InputInterface $input;

    /** @var OutputInterface output */
    protected OutputInterface $output;

    /** @var SymfonyStyle io */
    protected SymfonyStyle $io;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command lists all the users registered in the application:
                  <info>php %command.full_name%</info>
                By default the command only displays the 50 most recent users. Set the number of
                results to display with the <comment>--max-results</comment> option:
                  <info>php %command.full_name%</info> <comment>--max-results=2000</comment>
                In addition to displaying the user list, you can also send this information to
                the email address specified in the <comment>--send-to</comment> option:
                  <info>php %command.full_name%</info> <comment>--send-to=fabien@symfony.com</comment>
                HELP
            )
            // commands can optionally define arguments and/or options (mandatory and optional)
            // see https://symfony.com/doc/current/components/console/console_arguments.html
            ->addOption(
                'action',                              // this is the name that users must type to pass this option (e.g. --action=doSomething)
                null,                                  // this is the optional shortcut of the option name, which usually is just a letter (e.g. `i`, so users pass it as `-i`); use it for commonly used options or options with long names
                InputOption::VALUE_OPTIONAL,           // this is the type of option (e.g. requires a value, can be passed more than once, etc. InputOption::VALUE_OPTIONAL | InputOption::VALUE_REQUIRED)
                'Composer update command',   // the option description displayed when showing the command help
                null                                   // the default value of the option (for those which allow to pass values)
            );
    }

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container     = $container;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
    }

    private function p(): string
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

        if (false) {
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
            throw new \RuntimeException(sprintf('[AURORA] Cannot write %s file on disk.', $phpUnitFile));
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
            $this->io->error(sprintf('[AURORA] _updateGeoIP2(%s) invalid type!', $type));
            return;
        }

        $this->io->comment(sprintf('%s Updating the <info>Maxmind GeoIP2/GeoIP2' . $type . '</info> ...', $this->p()));

        if (!isset($_ENV['SINDLA_AURORA_GEO_LITE2_COUNTRY']) || !isset($_ENV['SINDLA_AURORA_GEO_LITE2_CITY'])) {
            $this->io->warning('[AURORA] ... skip becaus SINDLA_AURORA_GEO_LITE2_COUNTRY or SINDLA_AURORA_GEO_LITE2_CITY are defined in .env[.local]');
            return;
        } else if ('Country' == $type && false === filter_var($_ENV['SINDLA_AURORA_GEO_LITE2_COUNTRY'], FILTER_VALIDATE_BOOLEAN)) {
            $this->io->comment('[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_COUNTRY=false');
            return;
        } else if ('City' == $type && false === filter_var($_ENV['SINDLA_AURORA_GEO_LITE2_CITY'], FILTER_VALIDATE_BOOLEAN)) {
            $this->io->comment('[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_CITY=false');
            return;
        }

        $tempDir           = (true ? sys_get_temp_dir() : $this->container->getParameter('aurora.tmp') . '/' . microtime(true));
        $maxmindDir        = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2';
        $maxmindLicenseKey = trim($this->container->getParameter('aurora.maxmind.license_key'));
        $destinationFile   = "{$maxmindDir}/GeoLite2{$type}.mmdb";

        if (empty($maxmindLicenseKey)) {
            $this->io->error("[AURORA] Maxmind license key is not set.");
            $this->io->error("[AURORA] Check `MAXMIND_LICENSE_KEY=` inside .env file.");
            return;
        }

        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true)) {
            throw new \RuntimeException("[AURORA] Cannot create temporary dir `{$tempDir}`");
        }

        if (!is_dir($maxmindDir)) {
            try {
                mkdir($maxmindDir, 0777, true);
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

        $tmpTarGz = "{$tempDir}/GeoLite2-{$type}.tar.gz";

        if (!file_put_contents($tmpTarGz, $tarGz)) {
            return $this->io->error(sprintf('[AURORA] Cannot write %s file on disk.', $tmpTarGz));
        }

        // Decompress from gz
        $pharError = false;
        try {
            $PharData = new \PharData($tmpTarGz);
        } catch (\UnexpectedValueException $e) {
            $pharError = true;
            throw new \Exception('[AURORA] Could not read .tar.gz file.');
        } catch (\BadMethodCallException $e) {
            $pharError = true;
            throw new \Exception('[AURORA] Something goes wrong with the .tar.gz file.');
        }
        finally {
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

        if (false) {
            $auroraRootDir = $this->container->getParameter('aurora.root'); // %kernel.project_dir%
            $auroraTmpDir  = $this->container->getParameter('aurora.tmp');  // %kernel.project_dir%/var/tmp

            // Can be: /tmp/domain.tld/public/static/compiled/ or /srv/domain.tld/public/static/compiled/
            $auroraCacheDirs = [
                preg_replace('~//+~', '/', ($auroraTmpDir . '/compiled')),
                preg_replace('~//+~', '/', ($auroraRootDir . '/public/static/compiled'))
            ];

            foreach ($auroraCacheDirs as $auroraCacheDir) {
                if (!is_dir($auroraCacheDir) && !mkdir($auroraCacheDir, 0777, true)) {
                    throw new \RuntimeException("[AURORA] Cannot create cache dir `{$auroraCacheDir}`");
                } else {
                    /** @var IO $IOService */
                    $IOService = $this->container->get('aurora.io');

                    foreach (glob($auroraCacheDir . '/', GLOB_ONLYDIR) as $directory) {
                        $IOService->recursiveDelete($directory, false);
                    }
                }
            }
        }

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // Static compiled JS & CSS files (v2)

        $compiledDir = $this->container->getParameter('aurora.root') . '/public/static/compiled';

        if ($files = glob("{$compiledDir}/*.{css,js}", GLOB_BRACE)) {
            /** @var IO $IOService */
            $IOService = $this->container->get('aurora.io');
            foreach ($files as $file) {
                if ($IOService->fileIsOlderThan($file, 30, IO::TIME_UNIT_DAYS)) {
                    unlink($file);
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
                $IOService->recursiveDelete($directory, false);
            }

            $this->io->comment(sprintf('%s ... done;', $this->p()));
        }
    }
}
