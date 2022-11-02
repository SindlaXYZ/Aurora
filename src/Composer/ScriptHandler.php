<?php

namespace Sindla\Bundle\AuroraBundle\Composer;

use Symfony\Component\ClassLoader\ClassCollectionLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use Composer\Script\Event;

class ScriptHandler
{
    /**
     * Composer variables are declared static so that an event could update
     * a composer.json and set new options, making them immediately available
     * to forthcoming listeners.
     */
    protected static array $options
        = [
            'symfony-app-dir'        => 'app',
            'symfony-web-dir'        => 'web',
            'symfony-assets-install' => 'hard',
            'symfony-cache-warmup'   => false,
        ];

    protected static function getOptions(Event $event): array
    {
        $options = array_merge(static::$options, $event->getComposer()->getPackage()->getExtra());

        $options['symfony-assets-install'] = getenv('SYMFONY_ASSETS_INSTALL') ?: $options['symfony-assets-install'];
        $options['symfony-cache-warmup']   = getenv('SYMFONY_CACHE_WARMUP') ?: $options['symfony-cache-warmup'];

        $options['process-timeout'] = $event->getComposer()->getConfig()->get('process-timeout');
        $options['vendor-dir']      = $event->getComposer()->getConfig()->get('vendor-dir');

        return $options;
    }

    /**
     * @param Event $event
     */
    public static function postInstall(Event $event): void
    {
        $options          = static::getOptions($event);

        // Run the ComposerCommand [composer:run]
        static::executeCommand($event, 'bin', 'aurora:composer --action=postInstall', $options['process-timeout']);
    }

    /**
     * Installs the assets under the web root directory.
     *
     * For better interoperability, assets are copied instead of symlinked by default.
     *
     * Even if symlinks work on Windows, this is only true on Windows Vista and later,
     * but then, only when running the console with admin rights or when disabling the
     * strict user permission checks (which can be done on Windows 7 but not on Windows
     * Vista).
     *
     * @param Event $event
     */
    public static function postUpdate(Event $event): void
    {
        $options          = static::getOptions($event);

        // Run the ComposerCommand [composer:run]
        static::executeCommand($event, 'bin', 'aurora:composer --action=postUpdate', $options['process-timeout']);
    }

    protected static function getPhp($includeArgs = true): string|false
    {
        $phpFinder = new PhpExecutableFinder();
        if (!$phpPath = $phpFinder->find($includeArgs)) {
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    protected static function getPhpArguments(): array
    {
        $ini       = null;
        $arguments = [];

        $phpFinder = new PhpExecutableFinder();
        if (method_exists($phpFinder, 'findArguments')) {
            $arguments = $phpFinder->findArguments();
        }

        if ($env = getenv('COMPOSER_ORIGINAL_INIS')) {
            $paths = explode(PATH_SEPARATOR, $env);
            $ini   = array_shift($paths);
        } else {
            $ini = php_ini_loaded_file();
        }

        if ($ini) {
            $arguments[] = '--php-ini=' . $ini;
        }

        return $arguments;
    }

    protected static function executeCommand(Event $event, $consoleDir, $cmd, $timeout = 300)
    {
        $php     = escapeshellarg(static::getPhp(false));
        $phpArgs = implode(' ', array_map('escapeshellarg', static::getPhpArguments()));
        $console = escapeshellarg($consoleDir . '/console');
        if ($event->getIO()->isDecorated()) {
            $console .= ' --ansi';
        }

        $process = new Process($php . ($phpArgs ? ' ' . $phpArgs : '') . ' ' . $console . ' ' . $cmd, null, null, null, $timeout);
        $process->run(function ($type, $buffer) use ($event) {
            $event->getIO()->write($buffer, false);
        });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf("An error occurred when executing the \"%s\" command:\n\n%s\n\n%s", escapeshellarg($cmd), self::removeDecoration($process->getOutput()), self::removeDecoration($process->getErrorOutput())));
        }
    }

    private static function removeDecoration($string): string
    {
        return preg_replace("/\033\[[^m]*m/", '', $string);
    }
}
