<?php

namespace Sindla\Bundle\AuroraBundle\Command;

// Symfony
# use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Doctrine
use Doctrine\ORM\EntityManager;

/**
 * Class CommandMiddleware
 * Utilities for all command diles
 */
class CommandMiddleware extends Command
{
    /** @var ContainerInterface */
    protected $container;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var SymfonyStyle */
    protected $io;

    protected $kernelRootDir;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * CommandMiddleware constructor.
     *
     * @param string $defaultName
     */
    public function __construct(string $defaultName)
    {
        parent::__construct($defaultName);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('null');
    }

    protected function setInput(InputInterface $input)
    {
        $this->input = $input;
    }

    protected function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    protected function output($message, $newLine = true)
    {
        return ($newLine) ? $this->output->writeln($message) : $this->output->write($message);
    }

    protected function outputWithTime($message, $appendTab = false)
    {
        $this->output->writeln( (($appendTab) ? "\n" : '') . "[" . date('H:i:s') . "] " . preg_replace('/[\r\n]+/', '', strip_tags($message)));
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
                $parts      = explode('\\', $matches[2]);
                $matches[2] = end($parts);
            }
        }

        if (isset($matches[2])) {
            return $matches[2];
        }

        return null;
    }

    public function canBeNull(string $docComment)
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