<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraPHPUnitCodeCoverageBadge;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraPHPUnitCodeCoverageBadge\AuroraPHPUnitCodeCoverageBadge;

class AuroraPHPUnitCodeCoverageBadgeTest extends TestCase
{
    public function testStatementsBadgeUsesCorrectRightBlockWidth(): void
    {
        $badge = new AuroraPHPUnitCodeCoverageBadge();

        $tempDirectory = sys_get_temp_dir() . '/aurora_badge_' . uniqid('', true);
        self::assertTrue(mkdir($tempDirectory));

        $cloverFilePath     = $tempDirectory . '/clover.xml';
        $coverageSvgPath    = $tempDirectory . '/coverage.svg';
        $statementsSvgPath  = $tempDirectory . '/statements.svg';

        $cloverXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
    <project>
        <metrics elements="10" coveredelements="6"/>
        <file name="Sample.php">
            <metrics statements="5" coveredstatements="3"/>
        </file>
    </project>
</coverage>
XML;

        file_put_contents($cloverFilePath, $cloverXml);

        try {
            $badge->generateCoverageBadges($cloverFilePath, $coverageSvgPath, $statementsSvgPath);

            $statementsSvg = file_get_contents($statementsSvgPath);
            self::assertIsString($statementsSvg);
            self::assertStringContainsString('M75 0h85v20H75z', $statementsSvg);
        } finally {
            @unlink($cloverFilePath);
            @unlink($coverageSvgPath);
            @unlink($statementsSvgPath);
            @rmdir($tempDirectory);
        }
    }
}
