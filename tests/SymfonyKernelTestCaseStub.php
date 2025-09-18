<?php

namespace Symfony\Bundle\FrameworkBundle\Test;

use PHPUnit\Framework\TestCase;

if (!class_exists(KernelTestCase::class, false)) {
    abstract class KernelTestCase extends TestCase
    {
        protected static function bootKernel(array $options = []): object
        {
            return new class {
                public function getContainer(): object
                {
                    return new class {
                    };
                }
            };
        }

        protected static function ensureKernelShutdown(): void
        {
        }
    }
}
