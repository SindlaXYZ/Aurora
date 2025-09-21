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

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} else if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}
