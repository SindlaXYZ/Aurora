<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sindla\Bundle\AuroraBundle\Attribute\FormElement;
use Sindla\Bundle\AuroraBundle\Tests\Trait\PersistenceTrait;
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;

/**
 * echo "DB Reset + /Entity/ files" ; APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:schema:drop --full-database --force --no-interaction ; APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:schema:create --no-interaction ; APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:fixtures:load --no-interaction
 * echo "DB Reset + /Migrations/ files" ; APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:schema:drop --full-database --force; yes | APP_ENV=test APP_DEBUG=0 /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:migrations:migrate; yes | APP_ENV=test /usr/bin/php /srv/${DKZ_DOMAIN}/bin/console doctrine:fixtures:load --verbose --append
 *
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/ --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/GivenTest.php --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=         /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/GivenTest.php --no-coverage --stop-on-failure
 *
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/ --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/GivenTest.php --no-coverage
 * clear; cd /srv/${DKZ_DOMAIN}/; SYMFONY_DEPRECATIONS_HELPER=disabled /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/GivenTest.php --no-coverage --stop-on-failure
 */
#[CoversClass(FormElement::class)]
class ExpectedTest extends WebTestCaseMiddleware
{
    use PersistenceTrait;

    // clear; cd /srv/${DKZ_DOMAIN}/; /usr/bin/php bin/phpunit -c phpunit.xml.dist tests/Integration/GivenTest.php --no-coverage --do-not-cache-result --display-phpunit-notices --display-phpunit-deprecations --testdox --filter testCompanyConfigRelation
    #[Test]
    public function testCompanyConfigRelation(): void
    {
        $this->assertTrue(true);
    }
}
