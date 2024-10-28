<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use App\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Utils\CloudflareR2\CloudflareR2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsCommand(
    name       : 'aurora:cloudflare:r2',
    description: 'Cloudflare R2'
)]
final class CloudflareR2Command extends CommandMiddleware
{
    public function __construct(
        protected CloudflareR2 $cloudflareR2
    )
    {
        parent::__construct();
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

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->try($input, $output, $this);
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
