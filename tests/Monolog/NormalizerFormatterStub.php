<?php

declare(strict_types=1);

namespace Monolog\Formatter;

use DateTimeInterface;
use Monolog\LogRecord;

if (!class_exists(NormalizerFormatter::class)) {
    class NormalizerFormatter
    {
        protected string $dateFormat;

        public function __construct(?string $dateFormat = null)
        {
            $this->dateFormat = $dateFormat ?? DateTimeInterface::RFC3339_EXTENDED;
        }

        public function format(LogRecord $record)
        {
            return $this->normalize($record->toArray());
        }

        protected function normalize(mixed $data): mixed
        {
            if ($data instanceof DateTimeInterface) {
                return $data->format($this->dateFormat);
            }

            if (is_array($data)) {
                $normalized = [];
                foreach ($data as $key => $value) {
                    $normalized[$key] = $this->normalize($value);
                }

                return $normalized;
            }

            if (is_object($data)) {
                return $this->normalize(get_object_vars($data));
            }

            return $data;
        }
    }
}
