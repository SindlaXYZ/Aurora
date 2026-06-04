<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Aws\Result;
use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2\AuroraCloudflareR2;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name       : 'aurora:cloudflare:r2',
    description: 'Cloudflare R2'
)]
final class CloudflareR2Command extends CommandMiddleware
{
    public function __construct(
        protected AuroraCloudflareR2 $cloudflareR2
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
            ->addOption('remoteFile', null, InputOption::VALUE_OPTIONAL)
            ->addOption('orderBy', null, InputOption::VALUE_OPTIONAL)
            ->addOption('orderDir', null, InputOption::VALUE_OPTIONAL);
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
        $this->outputWithTime(sprintf("[%s] It works!", $this->getName()));
        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list --orderBy=Size --orderDir=desc
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list --orderBy=LastModified --orderDir=asc
     */
    protected function list(): int
    {
        $orderBy  = $this->input->getOption('orderBy') ?? 'Key';
        $orderDir = strtolower($this->input->getOption('orderDir') ?? 'asc');

        $s3Client = $this->cloudflareR2->createClient();

        /** @var Result $contents */
        $contents = $s3Client->listObjectsV2([
            'Bucket' => $this->cloudflareR2->getBucket()
        ]);

        // Prepare and sort rows according to orderBy/orderDir
        $rows = $contents->toArray()['Contents'] ?? [];

        // Normalize and validate ordering
        $validOrderBy = ['Key', 'LastModified', 'ETag', 'Size', 'Bytes', 'StorageClass'];
        if (!in_array($orderBy, $validOrderBy, true)) {
            $orderBy = 'Key';
        }
        // Treat 'Bytes' as 'Size' since source data uses 'Size'
        $orderKey = $orderBy === 'Bytes' ? 'Size' : $orderBy;
        $orderDir = $orderDir === 'desc' ? 'desc' : 'asc';

        // Apply sorting if there are rows
        if (is_array($rows) && count($rows) > 1) {
            usort($rows, function (array $a, array $b) use ($orderKey, $orderDir): int {
                $va = $a[$orderKey] ?? null;
                $vb = $b[$orderKey] ?? null;

                // Convert values for consistent comparison
                if ($orderKey === 'LastModified') {
                    $va = $va instanceof \DateTimeInterface ? $va->getTimestamp() : 0;
                    $vb = $vb instanceof \DateTimeInterface ? $vb->getTimestamp() : 0;
                } else if ($orderKey === 'Size') {
                    $va = (int)($va ?? 0);
                    $vb = (int)($vb ?? 0);
                } else {
                    $va = strtolower((string)($va ?? ''));
                    $vb = strtolower((string)($vb ?? ''));
                }

                $cmp = 0;
                if ($va === $vb) {
                    $cmp = 0;
                } else if ($va < $vb) {
                    $cmp = -1;
                } else {
                    $cmp = 1;
                }

                return $orderDir === 'desc' ? -$cmp : $cmp;
            });
        }

        $table = new Table($this->output)->setHeaders(['Key', 'LastModified', 'Ago', 'ETag', 'Bytes', 'Size', 'StorageClass']);

        $now = new \DateTimeImmutable('now');
        foreach ($rows as $data) {
            $table->addRow([
                $data['Key'],
                $data['LastModified']->format('Y-m-d H:i:s'),
                $this->formatRelativeTime($data['LastModified'], $now),
                $data['ETag'],
                new TableCell(
                    $data['Size'],
                    [
                        'style' => new TableCellStyle([
                            'align' => 'right'
                        ])
                    ]
                ),
                new TableCell(
                    $this->humanFilesize($data['Size']),
                    [
                        'style' => new TableCellStyle([
                            'align' => 'right'
                        ])
                    ]
                ),
                $data['StorageClass'],
            ]);
        }

        $table->render();

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

    private function humanFilesize(int $bytes, int $decimals = 2): string
    {
        $sz     = 'BKMGTP';
        $factor = (int)floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    private function formatRelativeTime(\DateTimeInterface $when, ?\DateTimeInterface $now = null): string
    {
        $now  = $now ?? new \DateTimeImmutable('now');
        $diff = $when->diff($now);

        $units = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        $valueByUnit = [
            'y' => $diff->y,
            'm' => $diff->m,
            'd' => $diff->d,
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s,
        ];

        $firstKey = null;
        foreach (['y', 'm', 'd', 'h', 'i', 's'] as $k) {
            if ($valueByUnit[$k] > 0) {
                $firstKey = $k;
                break;
            }
        }

        if ($firstKey === null) {
            return 'just now';
        }

        $firstVal  = $valueByUnit[$firstKey];
        $firstUnit = $units[$firstKey] . ($firstVal === 1 ? '' : 's');

        $parts = [sprintf('%d %s', $firstVal, $firstUnit)];

        // For units larger than minutes, include only one subunit (and never seconds)
        $secondKey = null;
        if ($firstKey === 'y') {
            $secondKey = 'm';
        } else if ($firstKey === 'm') {
            $secondKey = 'd';
        } else if ($firstKey === 'd') {
            $secondKey = 'h';
        } else if ($firstKey === 'h') {
            $secondKey = 'i';
        }

        if ($secondKey !== null && ($valueByUnit[$secondKey] ?? 0) > 0) {
            $secondVal  = $valueByUnit[$secondKey];
            $secondUnit = $units[$secondKey] . ($secondVal === 1 ? '' : 's');
            $parts[]    = sprintf('and %d %s', $secondVal, $secondUnit);
        }

        $suffix = $diff->invert === 1 ? '' : ' ago';
        $prefix = $diff->invert === 1 ? 'in ' : '';

        return $prefix . implode(' ', $parts) . $suffix;
    }
}
