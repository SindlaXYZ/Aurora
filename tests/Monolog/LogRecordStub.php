<?php

declare(strict_types=1);

namespace Monolog;

use DateTimeImmutable;

if (!class_exists(LogRecord::class)) {
    class LogRecord
    {
        public function __construct(
            public DateTimeImmutable $datetime,
            public string $channel,
            public Level $level,
            public string $message,
            public array $context = [],
            public array $extra = [],
            public mixed $formatted = null,
        ) {
        }

        public function toArray(): array
        {
            return [
                'message' => $this->message,
                'context' => $this->context,
                'level' => $this->level->value,
                'level_name' => $this->level->getName(),
                'channel' => $this->channel,
                'datetime' => $this->datetime,
                'extra' => $this->extra,
            ];
        }
    }
}
