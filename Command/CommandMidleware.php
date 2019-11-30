<?php

namespace Sindla\Bundle\AuroraBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandMidleware
 * Utilities for all command diles
 * @package Caen\Command
 */
class CommandMidleware extends ContainerAwareCommand
{
    private $firstLine = true;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('null');
    }

	/**
	 * Display a progress based on parameters
	 *
	 * @param int $start
	 * @param int $itemsNo
	 * @param int $iteration
	 * @param OutputInterface $output
	 * @param bool $final
	 * @return console output
	 */
	protected function progress(int $start, int $itemsNo, int $iteration, OutputInterface $output, bool $final = false, array $stats = [])
	{
		// Not the final progress
		if(!$final) {
			$minutes = round((microtime(true) - $start) / 60);

			if($itemsNo <= 500) {
				$step = 0.50;    // every 50%

			} else if($itemsNo <= 2500) {
				$step = 0.20;    // every 20%

			} else if($itemsNo <= 10000) {
				$step = 0.10;    // every 10%

			} else if($itemsNo <= 25000) {
				$step = 0.05;    // every 5%

			} else {
				$step = 0.01;    // every 1%
			}

			for ($i = 0; $i <= 1; $i = $i + $step) {
				if ($i !== 0 && $iteration == (ceil($itemsNo * $i))) {
					$secondsPerItem = (($minutes * 60) / $iteration);
					$itemsLeft = ($itemsNo - $iteration);

					$output->writeln("\n\n\n- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ");
					$output->writeln("\n<fg=black;bg=cyan>". date('Y-m-d H:i:s') ."</>");
					$output->writeln("\n<fg=black;bg=cyan>" . intval($i * 100) . "% processed (" . intval($itemsNo - $itemsLeft) . "/" . $itemsNo . ") in {$minutes} minutes, ~" . round($secondsPerItem) . " seconds per item</>");
					$output->writeln("\n<fg=black;bg=cyan>ETA: " . round($secondsPerItem * $itemsLeft / 60) . " minutes</>");
					$output->writeln("\n- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - \n\n");
				}
			}

		// The final progress
		} else {
			$output->writeln("\n\n\n- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - ");
			$output->writeln("\n<fg=black;bg=cyan>Started: ". date('Y-m-d H:i:s', $start) ."</>");
			$output->writeln("\n<fg=black;bg=cyan>Ended:   ". date('Y-m-d H:i:s') ."</>");
			$minutes = round((microtime(true) - $start) / 60);
			$secondsPerItem = (($minutes * 60) / $itemsNo);
			$output->writeln("\n<fg=black;bg=cyan>100% processed ({$itemsNo}/{$itemsNo}) in {$minutes} minutes, ~" . round($secondsPerItem) . " seconds per item</>");

			if(count($stats) > 0) {
				$output->writeln("\n<fg=black;bg=cyan>Success: ". $stats[true] ."</>");
				$output->writeln("\n<fg=black;bg=cyan>Failed:  ". $stats[false] ."</>");
			}

			$output->writeln("\n- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - \n\n");
		}
	}

	protected function output($message, $inCron, OutputInterface $output)
	{
		if(!$inCron) {
			$output->writeln($message);
		} else {
            echo (false === $this->firstLine ? "\t[". date('H:i:s') ."] " : '') . preg_replace('/[\r\n]+/', '', strip_tags($message)) ."\n";
        }

        if($this->firstLine) {
            $this->firstLine = false;
        }
	}

    /**
     * Extract @var value from a doc document
     *
     * @param string $docComment
     * @return null|string
     */
    protected function getDocCommentVar(string $docComment): ?string
    {
        preg_match('/@var \\\?([a-zA-Z\[\]]*)/i', $docComment, $matches);

        if(isset($matches[1])) {
            return $matches[1];
        }

        return null;
    }

    protected function getDocDocumentORMType(string $docComment): ?string
    {
        preg_match('/@ORM(.*?)type="([a-zA-Z]*?)"/i', $docComment, $matches);

        if(isset($matches[2])) {
            return $matches[2];
        }

        return null;
    }

    protected function getDocDocumentORMTargetEntity(string $docComment, bool $fullyQualifiedClassName = false)
    {
        // Fully qualified class name
        if($fullyQualifiedClassName) {
            preg_match('/@ORM(.*?)targetEntity="(.*?)"/i', $docComment, $matches);

        // Class name only
        } else {
            preg_match('/@ORM(.*?)targetEntity="(.*?)"/i', $docComment, $matches);
            if(isset($matches[2])) {
                $parts = explode('\\', $matches[2]);
                $matches[2] = end($parts);
            }
        }

        if(isset($matches[2])) {
            return $matches[2];
        }

        return null;
    }

    public function canBeNull(string $docComment)
    {
        // @ORM\Column(name="supplier_sent_at", type="datetime", nullable=true)
        preg_match('/@ORM(.*?)nullable=(true|false)/i', $docComment, $matches);

        //print_r($matches);die;

        if(is_array($matches) && count($matches) >= 3) {
            return ($matches[2] == 'true') ? true : false;
        }

        return false;
    }
}