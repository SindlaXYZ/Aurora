<?php

declare(strict_types=1);

namespace Symfony\Bridge\Monolog;

if (!class_exists(Logger::class)) {
    class Logger
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
