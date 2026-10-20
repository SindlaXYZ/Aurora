<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCloudflareR2\AuroraCloudflareR2;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
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
            ->addOption('orderDir', null, InputOption::VALUE_OPTIONAL)
            ->addOption('prefix', null, InputOption::VALUE_OPTIONAL);
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
     * Every page of the listing is read (one page holds at most 1000 keys), then the keys are sorted and rendered once, in a table.
     *
     * "--prefix" lists only the keys starting with it, sent as given: nothing is added or trimmed (pass the trailing "/" of a "directory").
     * The "Key" column always holds the full key, prefix included. A given but empty "--prefix" is refused: it would list the whole bucket.
     *
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list --orderBy=Size --orderDir=desc
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list --orderBy=LastModified --orderDir=asc
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=list --prefix=snapshots/current/
     *
     * @throws \Exception
     */
    protected function list(): int
    {
        $orderBy  = $this->input->getOption('orderBy') ?? 'Key';
        $orderDir = strtolower($this->input->getOption('orderDir') ?? 'asc');
        $prefix   = $this->input->getOption('prefix');

        // An empty prefix matches every key: a caller deleting each listed key would empty the whole bucket ("0" or " " are valid prefixes)
        if ($this->isPrefixGiven() && (null === $prefix || '' === $prefix)) {
            throw new \Exception('The "--prefix" option must not be empty.');
        }

        $s3Client = $this->cloudflareR2->createClient();

        // The same Bucket and Prefix for every page, without a Delimiter: the keys of the "subdirectories" are listed as well
        $params = ['Bucket' => $this->cloudflareR2->getBucket()];
        if (null !== $prefix) {
            $params['Prefix'] = $prefix;
        }

        // The rows of every page, in the order of the server, then sorted according to orderBy/orderDir
        $rows       = [];
        $seenTokens = [];
        do {
            $page = $s3Client->listObjectsV2($params);
            array_push($rows, ...($page['Contents'] ?? []));

            $truncated = (bool)($page['IsTruncated'] ?? false);
            if ($truncated) {
                // Stopping there would silently hide the next keys, and a token already sent would list the same pages again, forever
                $token = $page['NextContinuationToken'] ?? null;
                if (null === $token || '' === $token || isset($seenTokens[$token])) {
                    throw new \RuntimeException('ListObjectsV2 returned a truncated page without a new continuation token.');
                }

                $seenTokens[$token]          = true;
                $params['ContinuationToken'] = $token;
            }
        } while ($truncated);

        // Normalize and validate ordering
        $validOrderBy = ['Key', 'LastModified', 'ETag', 'Size', 'Bytes', 'StorageClass'];
        if (!in_array($orderBy, $validOrderBy, true)) {
            $orderBy = 'Key';
        }
        // Treat 'Bytes' as 'Size' since source data uses 'Size'
        $orderKey = $orderBy === 'Bytes' ? 'Size' : $orderBy;
        $orderDir = $orderDir === 'desc' ? 'desc' : 'asc';

        // Apply sorting if there are rows
        if (1 < count($rows)) {
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

        // Downloaded next to the local file, then renamed: "SaveAs" wrote the error body of a failed request (e.g. the 404 of a typo in
        // "--remoteFile") or the part of an interrupted download over the local file, e.g. the database dump about to be imported
        $partialFile = sprintf('%s.%s.download', $localFile, bin2hex(random_bytes(4)));

        try {
            $s3Client = $this->cloudflareR2->createClient();
            $s3Client->getObject([
                'Bucket' => $this->cloudflareR2->getBucket(),
                'Key'    => $remoteFile,
                'SaveAs' => $partialFile,
            ]);

            // The owner, the group and the permissions of the replaced file are kept (e.g. a private dump, readable by the application user)
            new AuroraIO()->replaceFile($partialFile, $localFile);
        } finally {
            if (is_file($partialFile)) {
                unlink($partialFile);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Deletes exactly one object, the "--remoteFile" key, permanently: R2 has no versioning
     *
     * clear; /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console aurora:cloudflare:r2 --verbose --action=delete --remoteFile=snapshots/current/database.sql
     *
     * @throws \Exception
     */
    protected function delete(): int
    {
        // Refused rather than ignored, with or without a value: not to be mistaken for a delete of every key under a prefix
        if ($this->isPrefixGiven()) {
            throw new \Exception('The "--prefix" option can only be used with "--action=list".');
        }

        if (empty($remoteFile = $this->input->getOption('remoteFile'))) {
            throw new \Exception('Please provide a remote file name!');
        }

        // Never DeleteObjects: only the given key. An S3 error is not caught, so a failed delete ends with a non-zero exit code
        $s3Client = $this->cloudflareR2->createClient();
        $s3Client->deleteObject([
            'Bucket' => $this->cloudflareR2->getBucket(),
            'Key'    => $remoteFile,
        ]);

        return self::SUCCESS;
    }

    /**
     * "--prefix" was given, with or without a value (set by the caller or on the command line)
     */
    private function isPrefixGiven(): bool
    {
        return null !== $this->input->getOption('prefix') || $this->input->hasParameterOption('--prefix');
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
