<?php

namespace Sindla\Bundle\AuroraBundle\Command;

# use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CommandMiddleware
 * Utilities for all command files
 */
class CommandMiddleware extends Command
{
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

    /**
     * CommandMiddleware constructor.
     *
     * @param string $defaultName
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->setName('null');
    }

    protected function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    protected function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    protected function setBufferOutput(BufferedOutput $bufferedOutput): void
    {
        $this->bufferedOutput = $bufferedOutput;
    }

    protected function setIo(SymfonyStyle $io): void
    {
        $this->io = $io;
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
     * Extract @param string $docComment
     *
     * @return null|string
     * @var value from a doc document
     *
     */
    protected function getDocCommentVar(string $docComment): ?string
    {
        //v1: preg_match('/@var \\\?([a-zA-Z\[\]]*)/i', $docComment, $matches);

        // v2: (also, match the ?)
        //preg_match('/@var \\\?\??([a-zA-Z\[\]]*)/i', $docComment, $matches);

        // V3: (match ?\Datetime)
        preg_match('/@var \\\?\??\\\?([a-zA-Z\[\]]*)/i', $docComment, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return null;
    }

    protected function getDocDocumentORMType(string $docComment): ?string
    {
        preg_match('/@ORM(.*?)type="([a-zA-Z]*?)"/i', $docComment, $matches);

        if (isset($matches[2])) {
            return $matches[2];
        }

        return null;
    }

    protected function getDocDocumentORMTargetEntity(string $docComment, bool $fullyQualifiedClassName = false)
    {
        // Fully qualified class name
        if ($fullyQualifiedClassName) {
            preg_match('/@ORM(.*?)targetEntity="(.*?)"/i', $docComment, $matches);

            // Class name only
        } else {
            preg_match('/@ORM(.*?)targetEntity="(.*?)"/i', $docComment, $matches);
            if (isset($matches[2])) {
                $parts = explode('\\', $matches[2]);
                $matches[2] = end($parts);
            }
        }

        if (isset($matches[2])) {
            return $matches[2];
        }

        return null;
    }

    public function canBeNull(string $docComment): bool
    {
        // @ORM\Column(name="supplier_sent_at", type="datetime", nullable=true)
        preg_match('/@ORM(.*?)nullable=(true|false)/i', $docComment, $matches);

        //print_r($matches);die;

        if (is_array($matches) && count($matches) >= 3) {
            return ($matches[2] == 'true') ? true : false;
        }

        // If not set, default true (can be null)
        return true;
    }
}
