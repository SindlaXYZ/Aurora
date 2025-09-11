<?php declare(strict_types=1);

namespace Twig\Extension;

abstract class AbstractExtension {}

namespace Twig;

class Environment {}

namespace Symfony\Component\DependencyInjection;

class Container {}

namespace Symfony\Component\HttpFoundation;

class RequestStack {}

namespace Sindla\Bundle\AuroraBundle\Utils\AuroraHelper;

class AuroraHelper {}

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Twig;

// PHPUnit
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper;
use Sindla\Bundle\AuroraBundle\Utils\Twig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/Twig/UtilityExtensionTest.php --no-coverage
 */
class UtilityExtensionTest extends TestCase
{
    /**
     * Fake test. Do not delete this, otherwise Bitbucket Pipeline will fail
     */
    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function testFilterAge(): void
    {
        $extension = new UtilityExtension(
            $this->createMock(Container::class),
            $this->createMock(RequestStack::class),
            $this->createMock(Environment::class),
            $this->createMock(AuroraHelper::class)
        );

        $birthDate = new \DateTime('2000-05-01');
        $expected  = (new \DateTime())->diff($birthDate)->y;

        $this->assertSame($expected, $extension->filterAge($birthDate));
    }
}
