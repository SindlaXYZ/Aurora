<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Sindla\Bundle\AuroraBundle\Utils\CloudflareR2\CloudflareR2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CloudflareR2Command extends CommandMiddleware
{
    protected CloudflareR2 $cloudflareR2;

    protected static $defaultName = 'aurora:cloudflare:r2';

    public function __construct(
        ContainerInterface $container
    )
    {
        parent::__construct(self::$defaultName);
        $this->container     = $container;
        $this->kernelRootDir = $this->container->getParameter('kernel.project_dir');
        $this->cloudflareR2  = $this->container->get('aurora.cloudflare.r2');
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to test Cron Command service.')
            // Mandatory
            ->addOption('action', null, InputOption::VALUE_REQUIRED)
            ->addOption('localFile', null, InputOption::VALUE_OPTIONAL)
            ->addOption('remoteFile', null, InputOption::VALUE_OPTIONAL);
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

        return self::SUCCESS;
    }


    /**
     * Usage:
     *      * * * * * root APP_ENV=$ENV /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --sqlLimit=1 --action=test >> /srv/${DKZ_DOMAIN}/.envs/.logs/crontab/`date +\%Y-\%m-\%d`.log 2>&1
     *
     * Manual call:
     *      clear; APP_ENV=dev  /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=test
     *      clear; APP_ENV=prod /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=test
     */
    protected function test(): int
    {
        $this->outputWithTime(sprintf("[%s] It works!", $this->commandName));
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
