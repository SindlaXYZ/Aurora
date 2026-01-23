<?php

namespace Sindla\Bundle\AuroraBundle\Command\Middleware;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Parser;
use Symfony\Contracts\Service\Attribute\Required;

class CommandMiddleware extends Command
{
    // Protected properties
    protected InputInterface  $input;
    protected OutputInterface $output;
    protected SymfonyStyle    $io;

    #[Required]
    private ParameterBagInterface  $parameterBag;
    private BufferedOutput         $bufferedOutput;
    private                        $kernelRootDir;
    #[Required]
    private ManagerRegistry        $managerRegistry;
    #[Required]
    private EntityManagerInterface $em;
    private string                 $projectDir;
    private ?ProgressBar           $progressBar = null;
    private \DateTimeInterface     $progressBarPreviousDisplay;

    public function __construct(
    )
    {
        $this->progressBarPreviousDisplay = new \DateTimeImmutable();
        parent::__construct();
    }

//    /**
//     * Inject container using setter injection
//     * This method will be automatically called by Symfony's service container
//     */
//    #[Required]
//    public function setContainer(
//        #[Autowire(service: 'service_container')]
//        ContainerInterface $container
//    ): void
//    {
//        $this->container = $container;
//    }

    /**
     * Inject ManagerRegistry using setter injection
     */
    #[Required]
    public function setManagerRegistry(ManagerRegistry $managerRegistry): void
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * Inject EntityManager using setter injection
     */
    #[Required]
    public function setEntityManager(
        #[Autowire(service: 'doctrine.orm.entity_manager')]
        EntityManagerInterface $em
    ): void
    {
        $this->em = $em;
    }

    /**
     * Inject project directory using setter injection
     */
    #[Required]
    public function setProjectDir(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): void
    {
        $this->projectDir = $projectDir;
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

    ###################################################################################################################################################################################################
    ###   YAML parser   ###############################################################################################################################################################################

    /**
     * @throws \Exception
     */
    protected function readYamlFile($yamlFileName): array
    {
        $yamlContent = $this->readFile($yamlFileName);

        return $this->parseYamlContent($yamlContent);
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

    private function parseYamlContent(string $yamlContent): array
    {
        if (trim($yamlContent) === '') {
            return [];
        }

        if (class_exists(Parser::class)) {
            try {
                $parsed = (new Parser())->parse($yamlContent);
                if (is_array($parsed)) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // Fallback handled below.
            }
        }

        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            try {
                $parsed = \Symfony\Component\Yaml\Yaml::parse($yamlContent);
                if (is_array($parsed)) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // Fallback handled below.
            }
        }

        if (function_exists('yaml_parse')) {
            $parsed = yaml_parse($yamlContent);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return $this->parseSimpleYaml($yamlContent);
    }

    private function parseSimpleYaml(string $yamlContent): array
    {
        $result      = [];
        $stack       = [&$result];
        $indentStack = [-1];

        $lines = preg_split('/\r\n|\r|\n/', $yamlContent) ?: [];

        foreach ($lines as $rawLine) {
            if ($rawLine === null) {
                continue;
            }

            $trimmedLine = ltrim($rawLine, " \t");

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '#') || in_array($trimmedLine, ['---', '...'], true)) {
                continue;
            }

            $indent = strlen($rawLine) - strlen($trimmedLine);

            while (count($indentStack) > 1 && $indent <= end($indentStack)) {
                array_pop($indentStack);
                array_pop($stack);
            }

            $currentIndex = count($stack) - 1;
            $current      =& $stack[$currentIndex];
            $line         = $trimmedLine;

            if ($line[0] === '-') {
                $valuePart = trim(substr($line, 1));

                if (!is_array($current)) {
                    $current = [];
                }

                if ($valuePart === '') {
                    $current[]     = [];
                    $lastIndex     = array_key_last($current);
                    $stack[]       =& $current[$lastIndex];
                    $indentStack[] = $indent;
                    continue;
                }

                if ($this->looksLikeInlineMap($valuePart)) {
                    [$inlineKey, $inlineValue] = array_map('trim', explode(':', $valuePart, 2));
                    $item = [];

                    if ($inlineValue === '') {
                        $item[$inlineKey] = [];
                        $current[]        = $item;
                        $lastIndex        = array_key_last($current);
                        $stack[]          =& $current[$lastIndex][$inlineKey];
                        $indentStack[]    = $indent;
                        continue;
                    }

                    $item[$inlineKey] = $this->castSimpleYamlValue($inlineValue);
                    $current[]        = $item;
                    continue;
                }

                $current[] = $this->castSimpleYamlValue($valuePart);
                continue;
            }

            [$key, $valuePart] = array_pad(explode(':', $line, 2), 2, null);
            $key = trim((string)$key);

            if ($valuePart === null) {
                $current[$key] = null;
                continue;
            }

            $valuePart = trim($valuePart);

            if ($valuePart === '') {
                $current[$key] = [];
                $stack[]       =& $current[$key];
                $indentStack[] = $indent;
                continue;
            }

            $current[$key] = $this->castSimpleYamlValue($valuePart);
        }

        return $result;
    }

    private function looksLikeInlineMap(string $valuePart): bool
    {
        $colonPosition = strpos($valuePart, ':');

        if ($colonPosition === false) {
            return false;
        }

        $firstChar = $valuePart[0];
        if ($firstChar === '"' || $firstChar === '\'') {
            return false;
        }

        $nextChar = $valuePart[$colonPosition + 1] ?? '';
        if ($nextChar !== '' && $nextChar !== ' ') {
            return false;
        }

        return true;
    }

    private function castSimpleYamlValue(string $value): mixed
    {
        $length = strlen($value);

        if ($length >= 2) {
            $firstChar = $value[0];
            $lastChar  = $value[$length - 1];

            if ($firstChar === '"' && $lastChar === '"') {
                return stripcslashes(substr($value, 1, -1));
            }

            if ($firstChar === '\'' && $lastChar === '\'') {
                $unquoted = substr($value, 1, -1);
                return str_replace("''", "'", $unquoted);
            }
        }

        if (preg_match('/^\[(.*)]$/', $value, $matches) === 1) {
            $items = $matches[1] === '' ? [] : array_map('trim', explode(',', $matches[1]));

            return array_values(array_map(
                fn(string $item) => $this->castSimpleYamlValue($item),
                array_filter($items, static fn(string $item) => $item !== '')
            ));
        }

        $lowerValue = strtolower($value);

        return match ($lowerValue) {
            'true', 'yes', 'on'  => true,
            'false', 'no', 'off' => false,
            'null', '~'          => null,
            default              => $this->castNumericValue($value),
        };
    }

    private function castNumericValue(string $value): mixed
    {
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return $value;
    }

    ###################################################################################################################################################################################################
    ###################################################################################################################################################################################################

    /**
     * Returns true if run from CLI (terminal), false if run from CRON/cronjob/background process
     */
    protected function hasTty(): bool
    {
        // Check if STDIN is defined and is a TTY
        if (!defined('STDIN')) {
            return false;
        }

        // Check if posix functions are available
        if (!function_exists('posix_isatty')) {
            // Fallback: assume it's not TTY if we can't check
            return false;
        }

        return posix_isatty(STDIN);
    }

    ###################################################################################################################################################################################################
    ###   Progress bar   ##############################################################################################################################################################################

    protected function createProgressBar(int $max): ProgressBar
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated since v8.0. Use progressBarCreate() instead.', E_USER_DEPRECATED);
        return $this->progressBarCreate($max);
    }

    protected function progressBarCreate(int $max): ProgressBar
    {
        $this->progressBar = $this->io->createProgressBar($max);
        $this->progressBar->setFormat("\n %current%/%max% [%bar%] %percent:3s%% in %elapsed:6s% / ETT %estimated:-16s% / ETA %remaining:-16s% / %memory:6s% \n %message%\n");
        $this->progressBar->setOverwrite(true);
        return $this->progressBar;
    }

    protected function progressBarAdvanceMessage(string $message, int $step = 1, bool $displayAllTimes = true): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->setMessage($message);
        $this->progressBar->advance($step);

        if (
            $displayAllTimes
            || $this->progressBarPreviousDisplay->getTimestamp() < new \DateTimeImmutable()->getTimestamp()
            || ($this->progressBar->getMaxSteps() == $this->progressBar->getProgress())
        ) {
            $this->progressBar->display();
        }

        $this->progressBarPreviousDisplay = new \DateTimeImmutable();
    }

    protected function progressBarGetElapsedSeconds(): int
    {
        if (null === $this->progressBar) {
            return 0;
        }

        return new \DateTimeImmutable()->getTimestamp() - $this->progressBar->getStartTime();
    }

    protected function progressBarGetElapsedMinutes(): int|float
    {
        if (null === $this->progressBar) {
            return 0;
        }

        return $this->progressBarGetElapsedSeconds() / 60;
    }

    protected function progressBarGetElapsedHours(): int|float
    {
        if (null === $this->progressBar) {
            return 0;
        }

        return $this->progressBarGetElapsedSeconds() / 3600;
    }

    protected function progressBarGetElapsedDays(): int|float
    {
        if (null === $this->progressBar) {
            return 0;
        }

        return $this->progressBarGetElapsedSeconds() / 86400;
    }

    protected function progressBarComment(string $comment, int $step = 0): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->clear();
        $this->io->comment($comment);
        if ($step > 0) {
            $this->progressBar->advance($step);
        }
        $this->progressBar->display();
    }

    protected function progressBarInfo(string $info, int $step = 0): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->clear();
        $this->io->info($info);
        if ($step > 0) {
            $this->progressBar->advance($step);
        }
        $this->progressBar->display();
    }

    protected function progressBarWarning(string $warning, int $step = 0): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->clear();
        $this->io->warning($warning);
        if ($step > 0) {
            $this->progressBar->advance($step);
        }
        $this->progressBar->display();
    }

    protected function progressBarError(string $error, int $step = 0): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->clear();
        $this->io->error($error);
        if ($step > 0) {
            $this->progressBar->advance($step);
        }
        $this->progressBar->display();
    }

    protected function progressBarSuccess(string $success, int $step = 0): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->clear();
        $this->io->success($success);
        if ($step > 0) {
            $this->progressBar->advance($step);
        }
        $this->progressBar->display();
    }

    protected function isFirstStep(): bool
    {
        if (null === $this->progressBar) {
            return true;
        }

        return $this->progressBar->getProgress() == 0;
    }

    protected function isLastStep(): bool
    {
        if (null === $this->progressBar) {
            return true;
        }

        return $this->progressBar->getProgress() == $this->progressBar->getMaxSteps();
    }

    protected function progressBarFinish(): void
    {
        if (null === $this->progressBar) {
            return;
        }

        $this->progressBar->setMessage('');
        $this->progressBar->clear();
        $this->progressBar->finish();
        $this->io->newLine();
    }

    ###################################################################################################################################################################################################
    ###   Database   ##################################################################################################################################################################################

    /**
     * @throws Exception
     */
    protected function databaseTableTruncate(string $tableName, bool $cascade = false): void
    {
        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();
        $connection->executeStatement($platform->getTruncateTableSQL($tableName, $cascade));
    }

    protected function databaseDrop(): void
    {
        $this->getApplication()->find('doctrine:schema:drop')
            ->run(new ArrayInput(['--full-database' => true, '--force' => true]), $this->output);
    }

    protected function databaseMigrate(): void
    {
        // Find the absolute path of the executable PHP binary (eg: /usr/bin/php | /usr/bin/php7.4 | ...)
        $phpBinaryFinder = new PhpExecutableFinder();
        $phpBinaryPath   = $phpBinaryFinder->find();
        $process         = new Process([
            $phpBinaryPath,
            sprintf('%s/bin/console', $this->projectDir),
            'doctrine:migrations:migrate',
            '-n'
        ]);
        $process->run();
    }

    protected function auditDropAndRecreateSchema(): void
    {
        $sqlDrop = 'DROP SCHEMA public CASCADE';
        $query   = $this->managerRegistry->getManager('audit')->getConnection()->prepare($sqlDrop);
        $query->executeQuery();

        $sqlCreate = 'CREATE SCHEMA public';
        $query     = $this->managerRegistry->getManager('audit')->getConnection()->prepare($sqlCreate);
        $query->executeQuery();
    }

    ###################################################################################################################################################################################################
    ###################################################################################################################################################################################################
}
