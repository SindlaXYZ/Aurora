<?php declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraMatch;

// PHPUnit
use PHPUnit\Framework\TestCase;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Sindla
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;

/**
 * clear; php phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/Utils/AuroraMatch/AuroraMatchTest.php --no-coverage
 */
class AuroraMatchTest extends KernelTestCase
{
    private $kernelTest;
    private $containerTest;

    protected function setUp(): void
    {
        $this->kernelTest    = self::bootKernel();
        $this->containerTest = $this->kernelTest->getContainer();
    }

    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    private function _matchDomain()
    {
        return [
            # true ------------------------------------------------------------------------
            [
                'needle'   => 'crawl-66-249-66-1.googlebot.com',
                'domain'   => 'googlebot.com',
                'expected' => true,
            ],
            # true ------------------------------------------------------------------------
            [
                'needle'   => 'http://sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'https://sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://www.sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'https://www.sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com/this/is/some/special/route',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com?another=special-route',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com?another=special-route&and=this',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com#this-is-really-special-route',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'www.sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            [
                'needle'   => 'sub.domain.sindla.com',
                'domain'   => 'sindla.com',
                'expected' => true,
            ],
            # false ------------------------------------------------------------------------
            [
                'needle'   => 'http://sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'https://sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'http://www.sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'https://www.sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'http://sub.domain.sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'www.sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'sub.domain.sindla.com.myscamdomain.info',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            # false ------------------------------------------------------------------------
            [
                'needle'   => 'http://notsindla.com',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'https://notsindla.com',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'notsindla.com',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'www.notsindla.com',
                'domain'   => 'sindla.com',
                'expected' => false,
            ],
            [
                'needle'   => 'sub.domain.notsindla.com',
                'domain'   => 'sindla.com',
                'expected' => false,
            ]
        ];
    }

    public function testMatchDomain()
    {
        $Match = new AuroraMatch();

        foreach ($this->_matchDomain() as $assertion) {
            if ($assertion['expected']) {
                $this->assertTrue($Match->matchDomain($assertion['needle'], $assertion['domain']), sprintf('%s & %s', $assertion['needle'], $assertion['domain']));
            } else {
                $this->assertFalse($Match->matchDomain($assertion['needle'], $assertion['domain']), sprintf('%s & %s', $assertion['needle'], $assertion['domain']));
            }
        }
    }

    public function testMatchAtLeastOneDomain()
    {
        $Match = new AuroraMatch();

        # true
        $this->assertTrue($Match->matchAtLeastOneDomain('sindla.com', ['sindla.com', 'sindla.ro']));
        $this->assertTrue($Match->matchAtLeastOneDomain('sindla.com', ['sindla.ro', 'sindla.com']));
        $this->assertTrue($Match->matchAtLeastOneDomain('crawl-66-249-66-1.googlebot.com', ['google.ro', 'googlebot.com', 'google.com']));

        # false
        $this->assertFalse($Match->matchAtLeastOneDomain('crawl-66-249-66-1.fakegooglebot.com', ['google.ro', 'googlebot.com', 'google.com']));
        $this->assertFalse($Match->matchAtLeastOneDomain('crawl-66-249-66-1.googlebot.com.myfakedomain.com', ['google.ro', 'googlebot.com', 'google.com']));
    }

    public function testMatchCssUrl()
    {
        $css     = '@import url("https://fonts.googleapis.com/css?family=Roboto:300,300i,400,500,700,900&display=swap");
/* line 1, _extend.scss */
.flex-center-start {
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -webkit-box-align: center;
  -ms-flex-align: center;
  align-items: center;
  -webkit-box-pack: start;
  -ms-flex-pack: start;
  justify-content: start;
}

/* Normal desktop :1200px. */
/* Normal desktop :992px. */
/* Tablet desktop :768px. */
/* small mobile :320px. */
/* Large Mobile :480px. */
/* 1. Theme default css */
/* line 4, _reset.scss */
body {
  font-family: "Roboto", sans-serif;
  font-weight: normal;
  font-style: normal;
}';
        $Match   = new AuroraMatch();
        $matches = $Match->matchCssUrls($css);

        $this->assertEmpty($matches[0]);
        $this->assertEmpty($matches[1]);
    }

    public function  testPasswordStrength()
    {
        $Match = new AuroraMatch();

        $this->assertFalse($Match->passwordStrength(''));
        $this->assertFalse($Match->passwordStrength(' '));
        $this->assertFalse($Match->passwordStrength('a'));
        $this->assertFalse($Match->passwordStrength('1'));
        $this->assertFalse($Match->passwordStrength('$'));

        $this->assertFalse($Match->passwordStrength('test', true, true, true));
        $this->assertFalse($Match->passwordStrength('TEST', true, true, true));
        $this->assertFalse($Match->passwordStrength('TEst', true, true, true));
        $this->assertFalse($Match->passwordStrength('tes1', true, true, true));
        $this->assertTrue($Match->passwordStrength('Tes1', true, true, true));

        $this->assertFalse($Match->passwordStrength('Tes1', true, true, true, true));
        $this->assertTrue($Match->passwordStrength('Te$1', true, true, true, true));

        $this->assertFalse($Match->passwordStrength('Te$1', true, true, true, true, 6));
        $this->assertFalse($Match->passwordStrength('Tekj12g4jh24v23jh523jh5g', true, true, true, true, 6));
        $this->assertTrue($Match->passwordStrength('Tekj12g4jh24v23jh523jh5g', true, true, true, false, 6));
        $this->assertTrue($Match->passwordStrength('Te$1123321', true, true, true, true, 6));
        $this->assertFalse($Match->passwordStrength('Te$11233212214', true, true, true, true, 6,9));
    }
}