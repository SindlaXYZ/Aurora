<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Console;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Console\SymfonyStyleFactory;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymfonyStyleFactoryTest extends TestCase
{
    public function testFake(): void
    {
        self::assertTrue(true);
        self::assertFalse(false);
    }

    public function testSymfonyStyle(): void
    {
        $symfonyStyleFactory = new SymfonyStyleFactory();
        $symfonyStyle        = $symfonyStyleFactory->create();

        self::assertInstanceOf(SymfonyStyle::class, $symfonyStyle);
    }
}
