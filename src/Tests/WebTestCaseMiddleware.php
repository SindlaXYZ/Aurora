<?php

namespace Sindla\Bundle\AuroraBundle\Tests;

// Symfony
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Tests\TestClient;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Router;

// Doctrine
use Doctrine\ORM\EntityManager;

class WebTestCaseMiddleware extends WebTestCase
{
    /** @var EntityManager $em */
    protected $em;

    /** @var Router */
    protected $router;

    /** @var Client */
    protected $client;

    protected $domainName;
    protected $domainSecret;

    protected $roleSuperAdmin;
    protected $roleSonataAdmin;
    protected $roleUser;

    private $progressTotal;
    private $progressIndex   = 1;
    private $progressSplitAt = 63;

    protected function setUp(): void
    {
        $this->domainName = '__DOMAIN__';

        $this->client = static::createClient([], [
            //'HTTP_HOST' => '__DOMAIN__',
            //'X_UNIT_TESTING' => true // `true` to avoid sending email when run the tests
        ]);

        $this->router = $this->client->getContainer()->get('router');

        if (!$this->em && self::$container) {
            /** @var EntityManager $em */
            $this->em = self::$container->get('doctrine.orm.entity_manager');
        }
    }

    /**
     * Fake test. Do not delete this, otherwise Bitbucket Pipeline will fail
     */
    public function testFake()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function progressStart(int $count)
    {
        $this->progressTotal = $count;
    }

    public function progressAdvance()
    {
        if(1 == $this->progressIndex) {
            fwrite(STDERR, "\nRun {$this->getName()}() tests ...\n");
        }

        fwrite(STDERR, '.');

        if ($this->progressIndex % $this->progressSplitAt == 0 || $this->progressIndex == $this->progressTotal) {

            $dots          = ($this->progressIndex % $this->progressSplitAt);
            $remainingDots = ($dots > 0 ? $this->progressSplitAt - $dots : 0);

            // Break every $splitAt chars, or at the end of iteration
            fwrite(STDERR, " " . str_pad($this->progressIndex, ($remainingDots + strlen($this->progressTotal)), ' ', STR_PAD_LEFT) . "/{$this->progressTotal}\n");
        }

        $this->progressIndex = $this->progressIndex + 1;
    }
}