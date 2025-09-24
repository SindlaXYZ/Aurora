<?php

namespace App\Tests\Integration;

use App\Entity\Company;
use App\Entity\CompanyConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sindla\Bundle\AuroraBundle\Tests\Trait\PersistenceTrait;
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

/**
 * APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:schema:drop --full-database --force; yes | APP_ENV=test APP_DEBUG=0 /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:migrations:migrate | APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:fixtures:load --verbose --append
 *
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/ --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/Given.php --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/Given.php --no-coverage --stop-on-failure
 *
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/ --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/Given.php --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/Given.php --no-coverage --stop-on-failure
 */
#[CoversClass(CompanyConfig::class)]
class MyTest extends WebTestCaseMiddleware
{
    use PersistenceTrait;

    // clear; cd /srv/${DKZ_DOMAIN}/; /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/Given.php --no-coverage --do-not-cache-result --testdox --filter testCompanyConfigRelation
    #[Test]
    public function testCompanyConfigRelation(): void
    {
        // Some logic here
    }
}
