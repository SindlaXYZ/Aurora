<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;

#[AsCommand(
    name       : 'aurora:composer',
    description: 'Composer update command',
    aliases    : ['aurora:composer']
)]
final class ComposerCommand extends Command
{
    /**
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:composer
     */
    protected InputInterface  $input;
    protected OutputInterface $output;
    protected SymfonyStyle    $io;
    protected string          $kernelRootDir;

    private const  GEOIP2_COUNTRY = 'Country';
    private const  GEOIP2_CITY    = 'City';
    private const  GEOIP2_ASN     = 'ASN';

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
                'Composer update command',             // the option description displayed when showing the command help
                null                                   // the default value of the option (for those which allow to pass values)
            );
    }

    public function __construct(
        protected ContainerInterface $container
    )
    {
        parent::__construct();
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
    }

    private function p(): string
    {
        return '[AURORA]';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;
        $this->io     = new SymfonyStyle($this->input, $this->output);

        $this->io->success(sprintf('%s Start running %s', $this->p(), $this->getName()));

        $action = trim($input->getOption('action'));

        if (empty($action)) {
            $this->io->warning('Invalid action: not specified.');

            return Command::FAILURE;
        }

        if ('_' == substr($action, 0, 1)) {
            $this->io->warning("Invalid action {$action}()");

            return Command::FAILURE;
        }

        if (method_exists($this, $action)) {
            $this->io->comment("[AURORA] Start to execute {$action}()");
            $this->$action();
            $this->io->newLine();
            $this->io->success('[AURORA] All commands were successfully run (post update).');
        } else {
            $this->io->warning("Invalid action {$action}()");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * clear; php bin/console aurora:composer --action=postInstall
     */
    private function postInstall(): void
    {
        // GeoIP2Country
        $this->_updateGeoIP2(self::GEOIP2_COUNTRY);

        // GeoIP2City
        $this->_updateGeoIP2(self::GEOIP2_CITY);

        // GeoIP2ASN
        $this->_updateGeoIP2(self::GEOIP2_ASN);

        $this->_cleanUpAndChecks(__FUNCTION__);
    }

    /**
     * clear; php bin/console aurora:composer --action=postUpdate
     */
    private function postUpdate(): void
    {
        // PHPUnit
        $this->_updatePHPUnit();

        // GeoIP2Country
        $this->_updateGeoIP2(self::GEOIP2_COUNTRY);

        // GeoIP2City
        $this->_updateGeoIP2(self::GEOIP2_CITY);

        // GeoIP2ASN
        $this->_updateGeoIP2(self::GEOIP2_ASN);

        $this->_cleanUpAndChecks(__FUNCTION__);

        if (false) {
            // Copy /Static/js
            $this->io->newLine();
            $this->output->writeln(sprintf('%s Copy the <info>/Static/js/*</info> to <info>/web/static/js/aurora/</info>', $this->p()));

            /** @var AuroraIO $IOService */
            $IOService = $this->container->get('aurora.io');
            $IOService->recursiveCreateDirectory($this->kernelRootDir . '/web/static/aurora/js/');

            copy(realpath(dirname(__FILE__)) . '/../Static/js/f.adblock.js', $this->kernelRootDir . '/web/static/aurora/js/f.adblock.js');

            $this->io->comment(sprintf('%s ... done;', $this->p()));
        }
    }

    public function _updatePHPUnit(): void
    {
        $this->io->comment(sprintf('%s Updating the <info>PHPUnit</info> ...', $this->p()));

        $phpUnitFile = $this->kernelRootDir . '/vendor/phpunit/phpunit.phar';

        // If the file is not older than X time
        $cacheSeconds = (60 * 60 * 24);
        if (
            file_exists($phpUnitFile)
            && 0 != filesize($phpUnitFile)
            &&
            (
                intval($cacheSeconds) < 0
                || strtotime(sprintf('-%d seconds', $cacheSeconds)) <= new \SplFileInfo($phpUnitFile)->getMTime()
            )
        ) {
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
     * @param string $type Country|City|ASN
     * @throws \Exception
     */
    private function _updateGeoIP2(string $type, bool $retryOnPharError = true): void
    {
        if (!in_array($type, [self::GEOIP2_COUNTRY, self::GEOIP2_CITY, self::GEOIP2_ASN])) {
            $this->io->error(sprintf('[AURORA] _updateGeoIP2(%s) invalid type!', $type));
            return;
        }

        $this->io->comment(sprintf('%s Updating the <info>Maxmind GeoIP2/GeoIP2' . $type . '</info> ...', $this->p()));

        if (!isset($_ENV['SINDLA_AURORA_GEO_LITE2_COUNTRY']) || !isset($_ENV['SINDLA_AURORA_GEO_LITE2_CITY']) || !isset($_ENV['SINDLA_AURORA_GEO_LITE2_ASN'])) {
            $this->io->warning('[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_COUNTRY or SINDLA_AURORA_GEO_LITE2_CITY or SINDLA_AURORA_GEO_LITE2_ASN are not defined in .env[.local]');
            return;
        } else if (self::GEOIP2_COUNTRY == $type && !filter_var($_ENV['SINDLA_AURORA_GEO_LITE2_COUNTRY'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->io->comment('<warning>[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_COUNTRY=false</warning>');
            return;
        } else if (self::GEOIP2_CITY == $type && !filter_var($_ENV['SINDLA_AURORA_GEO_LITE2_CITY'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->io->comment('<warning>[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_CITY=false</warning>');
            return;
        } else if (self::GEOIP2_ASN == $type && !filter_var($_ENV['SINDLA_AURORA_GEO_LITE2_ASN'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->io->comment('<warning>[AURORA] ... skip because SINDLA_AURORA_GEO_LITE2_ASN=false</warning>');
            return;
        }

        $tempDir             = (true ? sys_get_temp_dir() : $this->container->getParameter('aurora.tmp')) . '/' . date('Y-m-d Hi') . '_' . microtime(true);
        $maxmindDir          = $this->container->getParameter('aurora.resources') . '/maxmind-geoip2';
        $maxmindLicenseKey   = trim($this->container->getParameter('aurora.maxmind.license_key'));
        $destinationFile     = "{$maxmindDir}/GeoLite2{$type}.mmdb";
        $originalFileContent = file_exists($destinationFile) ? file_get_contents($destinationFile) : null;

        if (empty($maxmindLicenseKey)) {
            $this->io->error("[AURORA] Maxmind license key is not set.");
            $this->io->error("[AURORA] Check `MAXMIND_LICENSE_KEY=` inside .env file.");
            return;
        }

        if (!is_dir($tempDir) && !mkdir($tempDir, 0777, true)) {
            throw new \RuntimeException(sprintf('[AURORA] Cannot create temporary dir "%s".', $tempDir));
        }

        if (!is_dir($maxmindDir)) {
            try {
                mkdir($maxmindDir, 0777, true);
            } catch (\Exception $e) {
                $this->io->error(sprintf('[AURORA] Cannot create maxmind dir "%s".', $maxmindDir));

                return;
            }
        }

        // If the file is not older than X time
        $cacheSeconds = (60 * 60 * 24);
        if (
            file_exists($destinationFile)
            && 0 != filesize($destinationFile)
            &&
            (
                intval($cacheSeconds) < 0
                || strtotime(sprintf('-%d seconds', $cacheSeconds)) <= new \SplFileInfo($destinationFile)->getMTime()
            )
        ) {
            $this->io->comment(sprintf('%s ... skip updating (GeoIP2/GeoLite2%s is too new)', $this->p(), $type));
            return;
        }

        try {
            $tarGz = fopen("https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-{$type}&license_key={$maxmindLicenseKey}&suffix=tar.gz", 'r');
        } catch (\Exception $e) {
            $this->io->error('[AURORA] Cannot download .tar.gz file from geolite.maxmind.com.');

            return;
        }

        $tmpTar   = "{$tempDir}/GeoLite2-{$type}.tar";
        $tmpTarGz = "{$tmpTar}.gz";

        if (file_exists($tmpTar)) {
            unlink($tmpTar);
        }

        if (file_exists($tmpTarGz)) {
            unlink($tmpTarGz);
        }

        if (!file_put_contents($tmpTarGz, $tarGz)) {
            if ($originalFileContent) {
                file_put_contents($destinationFile, $originalFileContent);
            }

            $this->io->error(sprintf('[AURORA] Cannot write %s file on disk.', $tmpTarGz));

            return;
        }

        // Decompress from gz; retry the whole download once when the archive is corrupted
        try {
            $PharData = new \PharData($tmpTarGz);
        } catch (\UnexpectedValueException|\BadMethodCallException $e) {
            if ($retryOnPharError) {
                $this->io->warning(sprintf('[AURORA] Could not read the .tar.gz file (%s); retrying once.', $e->getMessage()));
                $this->_updateGeoIP2($type, false);

                return;
            }

            throw new \Exception(sprintf('[AURORA] Could not read the .tar.gz file (%s).', $e->getMessage()), 0, $e);
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

    private function _cleanUpAndChecks(string $functionName): void
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
                    throw new \RuntimeException(sprintf('[AURORA] Cannot create cache dir "%s".', $auroraCacheDir));
                } else {
                    /** @var AuroraIO $IOService */
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
            /** @var AuroraIO $IOService */
            $IOService = $this->container->get('aurora.io');
            foreach ($files as $file) {
                if ($IOService->fileIsOlderThan($file, 30, AuroraIO::TIME_UNIT_DAYS)) {
                    unlink($file);
                }
            }
        }

        // - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
        // _clearTmpDir : Clear /var/tmp/*

        if ('postUpdate' == $functionName) {

            $this->io->comment(sprintf('%s Clearing the <info>/var/tmp/*</info> ...', $this->p()));

            /** @var AuroraIO $IOService */
            $IOService = $this->container->get('aurora.io');
            foreach (glob($this->container->getParameter('aurora.tmp') . '/', GLOB_ONLYDIR) as $directory) {
                $IOService->recursiveDelete($directory, false);
            }

            $this->io->comment(sprintf('%s ... done;', $this->p()));
        }
    }
}
