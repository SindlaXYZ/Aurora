<?php

use Symfony\Component\Dotenv\Dotenv;

if(is_file(dirname(__DIR__).'/vendor/autoload.php')) {
    require dirname(__DIR__) . '/vendor/autoload.php';
} else {
    require __DIR__.'/../vendor/autoload.php';
}

if (!class_exists(\Symfony\Bundle\FrameworkBundle\Test\KernelTestCase::class)) {
    require __DIR__ . '/SymfonyKernelTestCaseStub.php';
}

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
