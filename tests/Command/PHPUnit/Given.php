<?php

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\CompanyConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sindla\Bundle\AuroraBundle\Tests\Trait\PersistenceTrait;

#[CoversClass(CompanyConfig::class)]
class MyTest
{
    use PersistenceTrait;

    #[Test]
    public function testCompanyConfigRelation(): void
    {
        // Some logic here
    }
}
