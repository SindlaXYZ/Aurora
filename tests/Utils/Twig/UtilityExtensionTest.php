<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper as AuroraHelperUtils;
use Sindla\Bundle\AuroraBundle\Utils\Twig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
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
  
    /**
     * @dataProvider dataFilterAge
     */
    #[DataProvider('dataFilterAge')]
    public function testFilterAge(\DateTime $given, int $expected): void
    {
        $extension = new UtilityExtension(
            new Container(),
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $this->assertSame($expected, $extension->filterAge($given));
    }

    public static function dataFilterAge(): array
    {
        $reference = new \DateTime();

        $years = [];
        for ($i = 0; $i < 48; $i++) {
            $years[] = 1900 + (int) round($i * (2025 - 1900) / 47);
        }

        $data  = [];
        $index = 0;
        foreach (range(1, 12) as $month) {
            foreach ([1, 10, 20, 28] as $day) {
                $year = $years[$index++];
                $date = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
                $data[] = [$date, $reference->diff($date)->y];
            }
        }

        foreach (['2000-02-29', '2024-02-29'] as $extra) {
            $date   = new \DateTime($extra);
            $data[] = [$date, $reference->diff($date)->y];
        }

        return $data;
    }

    /**
     * @dataProvider dataGetHash
     */
    #[DataProvider('dataGetHash')]
    public function testGetHash(int $size, int $expectedLength): void
    {
        $extension = new UtilityExtension(
            new Container(),
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $hash = $extension->getHash($size);

        $this->assertSame($expectedLength, strlen($hash));
    }

    public static function dataGetHash(): array
    {
        return [
            [5, 5],
            [50, 40],
            [0, 0],
            [-5, 0],
        ];
    }

    public function testCompressJsOutputsSingleNonce(): void
    {
        $container = new Container();
        $container->setParameter('kernel.environment', 'prod');
        $container->set('aurora.git', new class {
            public function getHash(): string
            {
                return 'hash';
            }
        });

        $extension = new UtilityExtension(
            $container,
            new RequestStack(),
            $this->createStub(Environment::class),
            new AuroraHelperUtils()
        );

        $request = new \Symfony\Component\HttpFoundation\Request();

        ob_start();
        $extension->compressJs($request, false, false, 'file.js');
        $output = ob_get_clean();

        $this->assertSame(1, substr_count($output, 'nonce='));
    }
}
