<?php

namespace Sindla\Bundle\AuroraBundle\Command;


use Sindla\Bundle\AuroraBundle\Command\Middleware\CommandMiddleware;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge\AuroraPHPUnitCodeCoverageBadge;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name       : 'aurora:php-unit',
    description: 'Generate PHPUnit code coverage badge',
    aliases    : ['aurora:phpunit']
)]
final class PHPUnitCommand extends CommandMiddleware
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag
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
            ->addOption('junitXMLFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputCoverageSVGFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputStatementsSVGFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputPassingSVGFilePath', null, InputOption::VALUE_OPTIONAL)
            ->addOption('outputTestsSVGFilePath', null, InputOption::VALUE_OPTIONAL);
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
        $this->outputWithTime(sprintf("Application environment: %s", $this->parameterBag->get('kernel.environment')));
        $this->outputWithTime(sprintf("Project directory: %s", $this->parameterBag->get('kernel.project_dir')));
        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php bin/console aurora:php-unit --action=generatePHPUnitPassingBadge --junitXMLFilePath=.envs/.test-results/junit.xml --outputPassingSVGFilePath=.github/badges/phpunit.svg
     *
     * Update the phpunit.svg (PHPUnit X/Y) badge file - where X is the number of passing tests and Y is the total number of tests
     */
    protected function generatePHPUnitPassingBadge(): int
    {
        if (
            !($junitXMLFilePath = $this->input->getOption('junitXMLFilePath') ?? null)
            || !($outputPassingSVGFilePath = $this->input->getOption('outputPassingSVGFilePath') ?? null)
        ) {
            throw new \Exception('Missing required options.');
        }

        if (!file_exists($junitXMLFilePath)) {
            $junitXMLFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $junitXMLFilePath;
        }

        if (!file_exists($outputPassingSVGFilePath)) {
            $outputPassingSVGFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $outputPassingSVGFilePath;
        }

        new AuroraPHPUnitCodeCoverageBadge()->generatePHPUnitPassingBadge($junitXMLFilePath, $outputPassingSVGFilePath);

        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php bin/console aurora:php-unit --action=generatePHPUnitTestsBadge --junitXMLFilePath=.envs/.test-results/junit.xml --outputTestsSVGFilePath=.github/badges/phpunit-tests.svg
     *
     * Generate the phpunit-tests.svg (passing/failing) badge from junit.xml — avoids GitHub API timing issues
     */
    protected function generatePHPUnitTestsBadge(): int
    {
        if (
            !($junitXMLFilePath = $this->input->getOption('junitXMLFilePath') ?? null)
            || !($outputTestsSVGFilePath = $this->input->getOption('outputTestsSVGFilePath') ?? null)
        ) {
            throw new \Exception('Missing required options.');
        }

        if (!file_exists($junitXMLFilePath)) {
            $junitXMLFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $junitXMLFilePath;
        }

        if (!file_exists($outputTestsSVGFilePath)) {
            $outputTestsSVGFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $outputTestsSVGFilePath;
        }

        new AuroraPHPUnitCodeCoverageBadge()->generatePHPUnitTestsBadge($junitXMLFilePath, $outputTestsSVGFilePath);

        return self::SUCCESS;
    }

    /**
     * clear; /usr/bin/php bin/console aurora:php-unit --action=generatePHPUnitCodeCoverageBadge --cloverXMLFilePath=.envs/.test-results/clover.xml --outputCoverageSVGFilePath=.github/badges/coverage.svg --outputStatementsSVGFilePath=.github/badges/statements.svg
     *
     * Update the coverage.svg (Coverage ?%) and statements.svg (Statements ?/?) badges file
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

        if (!file_exists($cloverXMLFilePath)) {
            $cloverXMLFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $cloverXMLFilePath;
        }

        if (!file_exists($outputCoverageSVGFilePath)) {
            $outputCoverageSVGFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $outputCoverageSVGFilePath;
        }

        if (!file_exists($outputStatementsSVGFilePath)) {
            $outputStatementsSVGFilePath = $this->parameterBag->get('kernel.project_dir') . '/' . $outputStatementsSVGFilePath;
        }

        new AuroraPHPUnitCodeCoverageBadge()->generateCoverageBadges($cloverXMLFilePath, $outputCoverageSVGFilePath, $outputStatementsSVGFilePath);

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
 * APP_ENV=test /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:schema:drop --full-database --force; yes | APP_ENV=test APP_DEBUG=0 /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:migrations:migrate; yes | APP_ENV=test /usr/bin/php /srv/\${DKZ_DOMAIN}/bin/console doctrine:fixtures:load --verbose --append
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

        $createdClass   = 0;
        $updatedClass   = 0;
        $createdMethods = 0;
        $updatedMethods = 0;

        foreach ($finder as $file) {
            $fileName         = $file->getFilename();
            $filePath         = $file->getRealPath();
            $content          = file_get_contents($filePath);
            $relativePath     = trim(str_replace($fileName, '', $file->getRelativePathname()), '/');
            $relativeFilePath = trim($file->getRelativePathname(), '/');

            // Update comments at the class level
            $classCommentPattern = '/(?P<comment>\/\*\*(?:[^*]|\*(?!\/))*\*\/\s*)?(?P<attributes>(?:(?:^[ \t]*\#\[[^\r\n]*\]\r?\n))*)(?P<indent>^[ \t]*)class\s+(?P<signature>\w+\s+(?:extends\s+\w+(?:\\\\\w+)*(?:\s+implements[^{\r\n]+)?|implements[^{\r\n]+|[^\r\n]*))/m';
            $newClassComment     = sprintf($classCommentBlock, $relativePath, $fileName);
            $classUpdated        = false;

            $content = preg_replace_callback(
                $classCommentPattern,
                function (array $matches) use ($newClassComment, &$updatedClass, &$createdClass, &$classUpdated) {
                    if ($classUpdated) {
                        return $matches[0];
                    }

                    if (!str_contains($matches['signature'], 'WebTestCaseMiddleware')) {
                        return $matches[0];
                    }

                    $classUpdated = true;

                    if (!empty($matches['comment'])) {
                        $updatedClass++;
                    } else {
                        $createdClass++;
                    }

                    $attributes = $matches['attributes'] ?? '';
                    $indent     = $matches['indent'] ?? '';
                    $signature  = $matches['signature'];

                    return rtrim($newClassComment) . "\n" . $attributes . $indent . 'class ' . $signature;
                },
                $content,
                1
            );

            if (!$classUpdated) {
                $this->io->warning(sprintf('The test file %s does not contain a class that extends the WebTestCaseMiddleware.', $file->getRelativePathname()));
                continue;
            }

            // Update comments for test methods
            $content = $this->updateTestMethodComments($content, $relativeFilePath);

            // Counting updated/created methods
            preg_match_all('/public function (test\w+)\(\)/', $content, $methodMatches);
            if (!empty($methodMatches[1])) {
                $methodCount = count($methodMatches[1]);

                // Check if comments have been added (simplistic)
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
     * Update the comments for the test methods
     */
    private function updateTestMethodComments(string $content, string $relativeFilePath): string
    {
        // 1) Delete any `// clear; cd` comment block located immediately above the test method
        //    Allow attributes (#\[] / #\[Attribute]) and empty lines between the comment and the method signature.
        $deletePattern = '~
        (^[ \t]*(?:\/\/\sclear;\scd\s[^\r\n]*\r?\n)+)           # blocul de linii `//`
        (?=                                       # lookahead: urmează doar atribute/linii goale și apoi metoda test
            (?:^[ \t]*\#\[[^\r\n]*\]\r?\n|        # linie cu atribut PHP 8: # [...]
               ^[ \t]*\#\s*[^\r\n]*\r?\n|         # linie cu # ... (coment/atribut simplu)
               ^[ \t]*\r?\n                       # linie goală
            )*
            ^[ \t]*public[ \t]+function[ \t]+test\w+[ \t]*\(
        )
    ~mx';

        $content = preg_replace($deletePattern, '', $content);

        // 2) Insert the correct comment before each test method
        $lines                = explode("\n", $content);
        $lineCount            = count($lines);
        $endsWithNewLine      = str_ends_with($content, "\n");
        $methodPattern        = '/^([ \t]*)public[ \t]+function[ \t]+(test\w+)[ \t]*\(/';
        $attributeLinePattern = '/^[ \t]*\#\[[^\r\n]*\]$/';

        for ($index = 0; $index < $lineCount; $index++) {
            $line = $lines[$index];

            if (!preg_match($methodPattern, $line, $methodMatch)) {
                continue;
            }

            $indent      = $methodMatch[1];
            $methodName  = $methodMatch[2];
            $insertIndex = $index;

            while ($insertIndex > 0) {
                $previousLine = $lines[$insertIndex - 1];

                if ($previousLine === '') {
                    break;
                }

                if (preg_match($attributeLinePattern, $previousLine)) {
                    $insertIndex--;
                    continue;
                }

                break;
            }

            $commentLine = sprintf(
                '%s// clear; cd /srv/${DKZ_DOMAIN}/; /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/%s --no-coverage --do-not-cache-result --display-phpunit-notices --display-phpunit-deprecations --testdox --filter %s',
                $indent,
                $relativeFilePath,
                $methodName
            );

            array_splice($lines, $insertIndex, 0, [$commentLine]);
            $lineCount++;
            $index++;
        }

        $content = implode("\n", $lines);

        if ($endsWithNewLine && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        return $content;
    }
}
