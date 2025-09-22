<?php

declare(strict_types=1);

namespace Symfony\Bridge\Monolog;

if (!class_exists(Logger::class)) {
    class Logger
    {
        public const DEBUG = 100;
        public const INFO = 200;
        public const NOTICE = 250;
        public const WARNING = 300;
        public const ERROR = 400;
        public const CRITICAL = 500;
        public const ALERT = 550;
        public const EMERGENCY = 600;
    }
}
