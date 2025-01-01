<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Sindla\Bundle\AuroraBundle\Utils\CloudflareR2\CloudflareR2;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CloudflareR2Command extends Command
{
    /**
     * The name of the command (the part after "bin/console")
     * The command must be registered in src/Resources/config/services.yaml
     *
     * Usage:
     *      clear; php bin/console aurora:cloudflare:r2 --action=test
     */
    protected static $defaultName = 'aurora:cloudflare:r2';

    protected CloudflareR2 $cloudflareR2;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct(self::$defaultName);
        $this->container     = $container;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
        $this->cloudflareR2  = new CloudflareR2();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription('Cloudflare R2')
            ->setHelp('Cloudflare R2 command')
            ->addOption('action', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var InputInterface input */
        $this->input = $input;

        /** @var OutputInterface output */
        $this->output = $output;

        /** @var SymfonyStyle io */
        $this->io = new SymfonyStyle($this->input, $this->output);

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
            $this->io->comment("[AURORA] ... end executing {$action}()");
        } else {
            return $this->io->warning("Invalid action {$action}()");
        }

        return Command::SUCCESS;
    }

    /**
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=test
     */
    protected function test(): int
    {
        $this->io->success(sprintf("[%s] It works!", self::$defaultName));
        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list
     */
    protected function list(): int
    {
        $s3Client = $this->cloudflareR2->createClient();
        $contents = $s3Client->listObjectsV2([
            'Bucket' => $this->cloudflareR2->getBucket()
        ]);

        print_r($contents);

        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=upload --localFile=/srv/${DKZ_DOMAIN}/.envs/.db/database.sql --remoteFile=database.sql
     *
     * @throws \Exception
     */
    protected function upload(): int
    {
        if (!file_exists($localFile = $this->input->getOption('localFile'))) {
            throw new \Exception(sprintf('File "%s" not found!', $localFile));
        }

        if (empty($remoteFile = $this->input->getOption('remoteFile'))) {
            throw new \Exception('Please provide a remote file name!');
        }

        $s3Client = $this->cloudflareR2->createClient();
        $s3Client->upload(
            $this->cloudflareR2->getBucket(),
            $remoteFile,
            fopen($localFile, 'r'),
        );

        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=download --remoteFile=database.sql --localFile=/srv/${DKZ_DOMAIN}/.envs/.db/database.sql
     * /usr/bin/php /srv/$DKZ_DOMAIN/bin/console doctrine:schema:drop --full-database --force
     * pgImport
     *
     * @throws \Exception
     */
    protected function download(): int
    {
        if (empty($remoteFile = $this->input->getOption('remoteFile'))) {
            throw new \Exception('Please provide a remote file name!');
        }

        if (empty($localFile = $this->input->getOption('localFile'))) {
            throw new \Exception('Please provide a local file name!');
        }

        $s3Client = $this->cloudflareR2->createClient();
        $s3Client->getObject([
            'Bucket' => $this->cloudflareR2->getBucket(),
            'Key'    => $remoteFile,
            'SaveAs' => $localFile,
        ]);

        return self::SUCCESS;
    }
}