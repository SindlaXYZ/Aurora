<?php

namespace Sindla\Bundle\AuroraBundle\Command\Middleware;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Parser;

class CommandMiddleware extends Command
{
    protected string $commandName;

    /** @var ContainerInterface|null */
    protected ?ContainerInterface $container = null;

    /** @var InputInterface */
    protected InputInterface $input;

    /** @var OutputInterface */
    protected OutputInterface $output;

    /** @var BufferedOutput */
    protected BufferedOutput $bufferedOutput;

    /** @var SymfonyStyle */
    protected SymfonyStyle $io;

    protected $kernelRootDir;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $em;

    public function __construct()
    {
        $this->commandName = strtolower(str_replace('Command', '', (new \ReflectionClass($this))->getShortName()));
        parent::__construct();
    }

    /**
     * This optional method is the first one executed for a command after configure() and is useful to initialize properties based on the input arguments and options.
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        /** @var InputInterface input */
        $this->input = $input;

        /** @var OutputInterface output */
        $this->output = $output;

        /** @var SymfonyStyle io */
        $this->io = new SymfonyStyle($this->input, $this->output);

        if (isset($this->entityManager) && $this->entityManager instanceof EntityManagerInterface) {
            $this->em = $this->entityManager;
        } else if (isset($this->container) && $this->container instanceof ContainerInterface) {
            /** @var EntityManager em */
            $this->em = $this->container->get('doctrine')->getManager();
        }
    }

    protected function try(InputInterface $input, OutputInterface $output, Command $command): int
    {
        $action = trim($input->getOption('action'));

        if (empty($action)) {
            $this->outputWithTime("Invalid action: not specified.");
            return self::FAILURE;
        }

        if ('_' == substr($action, 0, 1)) {
            $this->io->error("Invalid action {$action}()");
            return self::FAILURE;
        }

        if (method_exists($command, $action)) {
            $this->io->write(sprintf("[%s] Start running <fg=white;options=bold>%s()</> from <fg=white;options=bold>%s</> command", date('H:i:s'), $action, $command->getName()), true);
            $executionStartTime      = microtime(true);
            $actionResult            = $command->$action();
            $executionElapsedSeconds = microtime(true) - $executionStartTime;
            $hours                   = str_pad(BigDecimal::of($executionElapsedSeconds)->dividedBy(3600, 0, RoundingMode::FLOOR), 2, 0, STR_PAD_LEFT);
            $minutes                 = str_pad(BigDecimal::of($executionElapsedSeconds)->dividedBy(60, 0, RoundingMode::FLOOR)->remainder(60), 2, 0, STR_PAD_LEFT);
            $seconds                 = str_pad(BigDecimal::of($executionElapsedSeconds)->remainder(60)->toScale(0, RoundingMode::FLOOR), 2, 0, STR_PAD_LEFT);
            $this->io->write(sprintf("[%s] Done in <fg=white;options=bold>%s</>", date('H:i:s'), "{$hours}:{$minutes}:{$seconds}"), true);
            return $actionResult;
        } else {
            $this->io->error("Invalid action {$action}()");
            return self::FAILURE;
        }
    }

    protected function output($message, $newLine = true)
    {
        return ($newLine) ? $this->output->writeln($message) : $this->output->write($message);
    }

    protected function outputWithTime($message, $appendTab = false)
    {
        $this->output->writeln((($appendTab) ? "\n" : '') . "[" . date('H:i:s') . "] " . preg_replace('/[\r\n]+/', '', strip_tags($message)));
    }

    /**
     * @throws \Exception
     */
    protected function readYamlFile($yamlFileName): array
    {
        $results = (new Parser())->parse($this->readFile($yamlFileName));

        return $results ?? [];
    }

    /**
     * @throws \Exception
     */
    protected function readFile(string $absoluteFilePath): string
    {
        if (!file_exists($absoluteFilePath)) {
            throw new \Exception(sprintf('File %s does not exists.', $absoluteFilePath));
        }

        return file_get_contents($absoluteFilePath);
    }

    protected function createProgressBar(int $max): ProgressBar
    {
        $progressBar = $this->io->createProgressBar($max);
        $progressBar->setFormat("\n %current%/%max% [%bar%] %percent:3s%% in %elapsed:6s% / ETA %estimated:-6s%/ %memory:6s% \n %message%\n");
        $progressBar->setOverwrite(true);
        return $progressBar;
    }

    /**
     * @throws Exception
     */
    protected function databaseTableTruncate(string $tableName): void
    {
        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();
        $connection->executeStatement($platform->getTruncateTableSQL($tableName, false /* whether to cascade */));
    }

    protected function databaseDrop(): void
    {
        ($this->getApplication()->find('doctrine:schema:drop'))->run(new ArrayInput(['--full-database' => true, '--force' => true]), $this->output);
    }

    protected function databaseMigrate(): void
    {
        // Find the absolute path of the executable PHP binary (eg: /usr/bin/php | /usr/bin/php7.4 | ...)
        $phpBinaryFinder = new PhpExecutableFinder();
        $phpBinaryPath   = $phpBinaryFinder->find();

        $process = new Process([
            $phpBinaryPath,
            sprintf('%s/bin/console', $this->container->getParameter('root')),
            'doctrine:migrations:migrate',
            '-n'
        ]);
        $process->run();
    }
}
