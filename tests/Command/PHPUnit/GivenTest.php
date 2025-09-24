<?php

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\CompanyConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sindla\Bundle\AuroraBundle\Tests\Trait\PersistenceTrait;
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

#[CoversClass(CompanyConfig::class)]
class GivenTest extends WebTestCaseMiddleware
{
    use PersistenceTrait;

    #[Test]
    public function testCompanyConfigRelation(): void
    {
        $this->assertTrue(true);
    }
}
