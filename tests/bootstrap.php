<?php

use Symfony\Component\Dotenv\Dotenv;

if (defined('PHPUNIT_COMPOSER_INSTALL') && is_file(PHPUNIT_COMPOSER_INSTALL)) {
    require PHPUNIT_COMPOSER_INSTALL;
} else if (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
} else if (is_file(dirname(__DIR__, 3) . '/autoload.php')) {
    require dirname(__DIR__, 3) . '/autoload.php';
} else if (is_file(dirname(__DIR__) . '/../../../autoload.php')) {
    require dirname(__DIR__) . '/../../../autoload.php';
} else if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else if (is_string(getcwd()) && is_file(getcwd() . '/vendor/autoload.php')) {
    require getcwd() . '/vendor/autoload.php';
} else {
    throw new \RuntimeException('Could not find the autoload.php file. Please run "composer install".');
}

if (!class_exists(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class)) {
    require __DIR__ . '/SymfonyKernelTestCaseStub.php';
}

if (!class_exists(\Twig\Extension\AbstractExtension::class)) {
    require_once __DIR__ . '/Twig/AbstractExtensionStub.php';
    class_alias(
        \Sindla\Bundle\AuroraBundle\Tests\Twig\AbstractExtensionStub::class,
        \Twig\Extension\AbstractExtension::class
    );
}

if (!enum_exists(\Monolog\Level::class)) {
    require_once __DIR__ . '/Monolog/LevelStub.php';
}

if (!class_exists(\Monolog\LogRecord::class)) {
    require_once __DIR__ . '/Monolog/LogRecordStub.php';
}

if (!class_exists(\Monolog\Formatter\NormalizerFormatter::class)) {
    require_once __DIR__ . '/Monolog/NormalizerFormatterStub.php';
}

if (!class_exists(\Symfony\Bridge\Monolog\Logger::class)) {
    $monologLoggerStub = __DIR__ . '/Monolog/LoggerStub.php';

    if (is_file($monologLoggerStub)) {
        require_once $monologLoggerStub;
    } else {
        if (!class_exists('AuroraTestsMonologLoggerStubFallback', false)) {
            class AuroraTestsMonologLoggerStubFallback
            {
                public const int DEBUG     = 100;
                public const int INFO      = 200;
                public const int NOTICE    = 250;
                public const int WARNING   = 300;
                public const int ERROR     = 400;
                public const int CRITICAL  = 500;
                public const int ALERT     = 550;
                public const int EMERGENCY = 600;
            }
        }

        class_alias('AuroraTestsMonologLoggerStubFallback', \Symfony\Bridge\Monolog\Logger::class);
    }
}

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} else if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');
}
