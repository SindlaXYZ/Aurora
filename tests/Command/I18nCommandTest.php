<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Command\I18nCommand;
use Sindla\Bundle\AuroraBundle\Utils\AuroraIO\AuroraIO;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\Translator;

/**
 * symfony/translation is not a dependency of the bundle: it is installed by the host application
 */
#[RequiresMethod(LocaleSwitcher::class, 'setLocale')]
class I18nCommandTest extends TestCase
{
    private string $defaultLocale;
    private string $translationsDir = '';

    protected function setUp(): void
    {
        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        // The locale switcher changes the default locale of the process
        \Locale::setDefault($this->defaultLocale);

        if ('' !== $this->translationsDir) {
            new AuroraIO()->recursiveDelete($this->translationsDir);
        }
    }

    public function testTheCommandDefinition(): void
    {
        $command = $this->createCommand();

        $this->assertSame('aurora:i18n', $command->getName());
        $this->assertSame(['aurora:internationalization'], $command->getAliases());
        $this->assertSame('Aurora i18n (internationalization) command', $command->getDescription());
        $this->assertSame('Aurora i18n command', $command->getHelp());
        $this->assertTrue($command->getDefinition()->getOption('action')->isValueRequired());
        $this->assertTrue($command->getDefinition()->getOption('locale')->isValueOptional());
    }

    public function testTheTestActionDescribesTheTranslatorAndSwitchesTheLocale(): void
    {
        $this->translationsDir = sprintf('%s/aurora-i18n-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        mkdir($this->translationsDir);
        foreach (['messages.en.yaml', 'validators.en.yaml', 'messages.ro.yaml'] as $file) {
            touch($this->translationsDir . '/' . $file);
        }

        $translator = new Translator('en');
        $container  = new Container();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', '/srv/example');
        $container->setParameter('locales', ['en', 'ro']);
        $container->setParameter('translator.default_path', $this->translationsDir);
        $container->set('translator', $translator);

        $tester = new CommandTester(new I18nCommand($container, $translator, new LocaleSwitcher('en', [$translator])));

        // The translation files of the default locale are dumped with print_r()
        $this->expectOutputString(print_r([$this->translationsDir . '/messages.en.yaml', $this->translationsDir . '/validators.en.yaml'], true));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'test']));
        $this->assertSame('ro', $translator->getLocale());

        $lines = array_values(array_filter(array_map(
            static fn(string $line): string => trim((string)preg_replace('/^\[\d{2}:\d{2}:\d{2}\] /', '', $line)),
            explode("\n", $tester->getDisplay())
        )));

        $this->assertSame([
            'Command: aurora:i18n',
            'Application environment: test',
            'Project directory: /srv/example',
            'Container locale: en',
            'Translator locale: en',
            'Translator locales: [ en, ro ]',
            sprintf('Translator default path: %s', $this->translationsDir),
            '// Call localeSwitcher',
            'Container locale: ro',
            'Translator locale: ro',
        ], array_slice($lines, 1, -1));
    }

    #[DataProvider('dataTheDumpActionExtractsTheTranslations')]
    public function testTheDumpActionExtractsTheTranslations(array $options, string $expectedLocale): void
    {
        $extractCommand = new class extends Command {
            /**
             * @var array<string, mixed>
             */
            public array $received = [];

            protected function configure(): void
            {
                $this
                    ->setName('translation:extract')
                    ->addArgument('locale', InputArgument::REQUIRED)
                    ->addOption('force', null, InputOption::VALUE_NONE)
                    ->addOption('format', null, InputOption::VALUE_REQUIRED);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->received = ['locale' => $input->getArgument('locale'), 'force' => $input->getOption('force'), 'format' => $input->getOption('format')];
                $output->writeln('Translations extracted.');

                return self::SUCCESS;
            }
        };

        $command     = $this->createCommand();
        $application = new Application();
        $application->addCommand($extractCommand);
        $application->addCommand($command);

        $tester = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--action' => 'dump'] + $options));
        $this->assertSame(['locale' => $expectedLocale, 'force' => true, 'format' => 'yaml'], $extractCommand->received);
        $this->assertStringContainsString('Translations extracted.', $tester->getDisplay());
    }

    public static function dataTheDumpActionExtractsTheTranslations(): array
    {
        return [
            'the English locale by default' => [[], 'en'],
            'the given locale'              => [['--locale' => 'ro'], 'ro'],
        ];
    }

    private function createCommand(): I18nCommand
    {
        $translator = new Translator('en');

        return new I18nCommand(new Container(), $translator, new LocaleSwitcher('en', [$translator]));
    }
}
