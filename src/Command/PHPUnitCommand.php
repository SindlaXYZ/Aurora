<?php

namespace Sindla\Bundle\AuroraBundle\Command;


use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge\AuroraPHPUnitCodeCoverageBadge;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name       : 'aurora:php-unit',
    description: 'Generate PHPUnit code coverage badge',
    aliases    : ['aurora:phpunit']
)]
final class PHPUnitCommand extends CommandMiddleware
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        protected ?ContainerInterface            $container,
        protected readonly ParameterBagInterface $parameterBag
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Aurora PHPUnit code coverage badge command')
            // Mandatory
            ->addOption('action', null, InputOption::VALUE_REQUIRED)
            // Optional
            ->addOption('cloverXMLFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputCoverageSVGFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputStatementsSVGFilePath', null, InputOption::VALUE_OPTIONAL);
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->try($input, $output, $this);
    }

    /**
     * Manual call:
     *      clear; /usr/bin/php bin/console aurora:php-unit --action=test
     */
    protected function test(): int
    {
        $this->outputWithTime(sprintf("Command: %s", $this->commandName));
        $this->outputWithTime(sprintf("Application environment: %s", $this->container->getParameter('kernel.environment')));
        $this->outputWithTime(sprintf("Project directory: %s", $this->container->getParameter('kernel.project_dir')));
        return self::SUCCESS;
    }

    /**
     * Manual call:
     *      clear; /usr/bin/php bin/console aurora:php-unit --action=generatePHPUnitCodeCoverageBadge --cloverXMLFilePath=build/logs/clover.xml --outputCoverageSVGFilePath=.github/badges/coverage.svg --outputStatementsSVGFilePath=.github/badges/statements.svg
     */
    protected function generatePHPUnitCodeCoverageBadge(): int
    {
        if (
            !($cloverXMLFilePath = $this->input->getOption('cloverXMLFilePath') ?? null)
            || !($outputCoverageSVGFilePath = $this->input->getOption('outputCoverageSVGFilePath') ?? null)
            || !($outputStatementsSVGFilePath = $this->input->getOption('outputStatementsSVGFilePath') ?? null)
        ) {
            throw new \Exception('Missing required options.');
        }

        $cloverXMLFilePath           = $this->container->getParameter('kernel.project_dir') . '/' . $cloverXMLFilePath;
        $outputCoverageSVGFilePath   = $this->container->getParameter('kernel.project_dir') . '/' . $outputCoverageSVGFilePath;
        $outputStatementsSVGFilePath = $this->container->getParameter('kernel.project_dir') . '/' . $outputStatementsSVGFilePath;

        new AuroraPHPUnitCodeCoverageBadge()->generate($cloverXMLFilePath, $outputCoverageSVGFilePath, $outputStatementsSVGFilePath);

        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php bin/console aurora:php-unit --action=updateDocumentationBlocks
     * clear; /usr/bin/php bin/console aurora:php-unit --verbose --action=updateDocumentationBlocks
     */
    protected function updateDocumentationBlocks(): int
    {
        $testDirectory = realpath($this->parameterBag->get('kernel.project_dir') . '/tests/');

        if (!is_dir($testDirectory)) {
            $this->io->error('Directory /test/ not found.');
            return self::FAILURE;
        }

        $finder = new Finder();
        $finder->files()->in($testDirectory)->name('*Test.php');

        if (!$finder->hasResults()) {
            $this->io->warning('No test files found in /test/ directory.');
            return self::SUCCESS;
        }

        $classCommentBlock = <<<COMMENT
/**
 * APP_ENV=test /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:schema:drop --full-database --force; yes | APP_ENV=test APP_DEBUG=0 /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:migrations:migrate | APP_ENV=test /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:fixtures:load --verbose --append
 *
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/ --no-coverage
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/%2\$s --no-coverage
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/%2\$s --no-coverage --stop-on-failure
 *
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/ --no-coverage
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/%2\$s --no-coverage
 * clear; cd /srv/\${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%1\$s/%2\$s --no-coverage --stop-on-failure
 */
COMMENT;

        $createdClass = 0;
        $updatedClass = 0;
        $createdMethods = 0;
        $updatedMethods = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);
            $relativePath = trim(str_replace($file->getFilename(), '', $file->getRelativePathname()), '/');
            $relativeFilePath = trim($file->getRelativePathname(), '/');

            // Actualizare comentarii la nivel de clasă
            $patternWithCommentBlock = '/(\/\*\*(?:[^*]|\*(?!\/))*\*\/)\s*class\s+(\w+) extends WebTestCaseMiddleware/s';
            $patternWithoutCommentBlock = '/class\s+(\w+) extends WebTestCaseMiddleware/';
            $newClassComment = sprintf($classCommentBlock, $relativePath, $file->getFilename());

            if (preg_match($patternWithCommentBlock, $content, $matches)) {
                $existingComment = $matches[1];
                $className = $matches[2];

                $content = preg_replace(
                    $patternWithCommentBlock,
                    $newClassComment . "\nclass $className extends WebTestCaseMiddleware",
                    $content
                );
                $updatedClass++;
            } else if (preg_match($patternWithoutCommentBlock, $content, $matches)) {
                $className = $matches[1];

                $content = preg_replace(
                    $patternWithoutCommentBlock,
                    $newClassComment . "\nclass $className extends WebTestCaseMiddleware",
                    $content
                );
                $createdClass++;
            } else {
                $this->io->warning(sprintf('The test file %s does not contain a class that extends the WebTestCaseMiddleware.', $file->getRelativePathname()));
                continue;
            }

            // Actualizare comentarii pentru metodele de test
            $content = $this->updateTestMethodComments($content, $relativeFilePath);

            // Numărare metode actualizate/create
            preg_match_all('/public function (test\w+)\(\)/', $content, $methodMatches);
            if (!empty($methodMatches[1])) {
                $methodCount = count($methodMatches[1]);

                // Verifică dacă au fost adăugate comentarii (simplist)
                $commentCount = substr_count($content, '--filter test');
                if ($commentCount > 0) {
                    $createdMethods += $methodCount;
                }
            }

            file_put_contents($filePath, $content);
        }

        $this->io->success(sprintf(
            'Comment blocks updated: %d class files updated, %d class files created, %d test methods processed.',
            $updatedClass,
            $createdClass,
            $createdMethods
        ));

        return self::SUCCESS;
    }

    /**
     * Actualizează comentariile pentru metodele de test
     */
    private function updateTestMethodComments(string $content, string $relativeFilePath): string
    {
        // Pattern pentru metodele de test cu sau fără comentarii existente
        $pattern = '/(\s*)(\/\*\*(?:[^*]|\*(?!\/))*\*\/)?\s*(public function (test\w+)\(\)[^{]*\{)/s';

        return preg_replace_callback($pattern, function ($matches) use ($relativeFilePath) {
            $indentation = $matches[1];
            $existingComment = $matches[2] ?? '';
            $methodDeclaration = $matches[3];
            $methodName = $matches[4];

            // Generează comentariul pentru metodă
            $methodComment = $this->generateMethodComment($relativeFilePath, $methodName, $indentation);

            // Dacă există deja un comentariu, îl înlocuiește
            if (!empty($existingComment)) {
                return $indentation . $methodComment . "\n" . $indentation . $methodDeclaration;
            } else {
                // Dacă nu există comentariu, îl adaugă
                return $indentation . $methodComment . "\n" . $indentation . $methodDeclaration;
            }
        }, $content);
    }

    /**
     * Generează comentariul pentru o metodă de test specifică
     */
    private function generateMethodComment(string $relativeFilePath, string $methodName, string $indentation = ''): string
    {
        $commentTemplate = <<<COMMENT
/**
 * clear; cd /srv/\${DKZ_DOMAIN}/; /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%s --no-coverage --do-not-cache-result --testdox --filter %s
 */
COMMENT;

        return sprintf($commentTemplate, $relativeFilePath, $methodName);
    }
}
