<?php

declare(strict_types=1);

namespace Monolog;

if (!enum_exists(Level::class)) {
    enum Level: int
    {
        case Debug = 100;
        case Info = 200;
        case Notice = 250;
        case Warning = 300;
        case Error = 400;
        case Critical = 500;
        case Alert = 550;
        case Emergency = 600;

        public static function fromName(string $name): self
        {
            return match (strtolower($name)) {
                'debug' => self::Debug,
                'info' => self::Info,
                'notice' => self::Notice,
                'warning' => self::Warning,
                'error' => self::Error,
                'critical' => self::Critical,
                'alert' => self::Alert,
                'emergency' => self::Emergency,
                default => throw new \InvalidArgumentException(sprintf('Unknown level name "%s".', $name)),
            };
        }

        public static function fromValue(int $value): self
        {
            return match ($value) {
                self::Debug->value => self::Debug,
                self::Info->value => self::Info,
                self::Notice->value => self::Notice,
                self::Warning->value => self::Warning,
                self::Error->value => self::Error,
                self::Critical->value => self::Critical,
                self::Alert->value => self::Alert,
                self::Emergency->value => self::Emergency,
                default => throw new \InvalidArgumentException(sprintf('Unknown level value "%d".', $value)),
            };
        }

        public function getName(): string
        {
            return match ($this) {
                self::Debug => 'DEBUG',
                self::Info => 'INFO',
                self::Notice => 'NOTICE',
                self::Warning => 'WARNING',
                self::Error => 'ERROR',
                self::Critical => 'CRITICAL',
                self::Alert => 'ALERT',
                self::Emergency => 'EMERGENCY',
            };
        }
    }
}
