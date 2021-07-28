<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Console;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Usage:
 *
 *      private ?SymfonyStyle $symfonyStyle;
 *
 *      public function __construct(SymfonyStyle $symfonyStyle)
 *      -OR-
 *      $symfonyStyleFactory = new SymfonyStyleFactory();
 *      $this->symfonyStyle  = $symfonyStyleFactory->create();
 */
final class SymfonyStyleFactory
{
    public function create(): SymfonyStyle
    {
        $input  = new ArgvInput();
        $output = new ConsoleOutput();

        return new SymfonyStyle($input, $output);
    }
}
