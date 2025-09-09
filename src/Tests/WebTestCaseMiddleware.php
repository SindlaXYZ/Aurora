<?php

namespace Sindla\Bundle\AuroraBundle\Tests;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class WebTestCaseMiddleware extends WebTestCase
{
    /** @var EntityManager $em */
    protected $em;

    /** @var Router */
    protected $router;

    /** @var KernelBrowser */
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

        if (!$this->em && static::getContainer()) {
            /** @var EntityManager $em */
            $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        }
    }

    /**
     * Login a user with raw password
     */
    protected function loginWithRawPassword(UserInterface $user, string $rawPassword): void
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        if ($hasher->isPasswordValid($user, $rawPassword)) {
            $this->loginWithoutValidation($user);
        }
    }

    /**
     * Login a user without any validation (just for tests)
     */
    protected function loginWithoutValidation(UserInterface $user): void
    {
        $token = new UsernamePasswordToken($user, $user->getPassword(), "database", $user->getRoles());
        static::getContainer()->get("security.token_storage")->setToken($token);
    }

    /**
     * Fake test. Do not delete this, otherwise Bitbucket Pipeline will fail
     */
    public function testFake(): void
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }

    public function progressStart(int $count): void
    {
        $this->progressTotal = $count;
        $this->progressIndex = 1;
    }

    public function progressAdvance(): void
    {
        if (1 == $this->progressIndex) {
            fwrite(STDERR, "\nRun " . ($this->getParentOrNull() ?? 'Unknown') . "() tests ...\n");
        }

        fwrite(STDERR, '.');

        if ($this->progressIndex % $this->progressSplitAt == 0 || $this->progressIndex == $this->progressTotal) {

            $dots          = ($this->progressIndex % $this->progressSplitAt);
            $remainingDots = ($dots > 0 ? $this->progressSplitAt - $dots : 0);

            // Break all $splitAt chars, or at the end of iteration
            fwrite(STDERR, " " . str_pad($this->progressIndex, ($remainingDots + strlen($this->progressTotal)), ' ', STR_PAD_LEFT) . "/{$this->progressTotal}\n");
        }

        $this->progressIndex = $this->progressIndex + 1;
    }

    /**
     * Command-line PHP Output colors
     *
     * https://joshtronic.com/2013/09/02/how-to-use-colors-in-command-line-output/
     *
     * Dev: php -r 'echo "\e[0;30;43mThis is a test message\e[0m\n";'
     *
     * ==Foreground Colors==
     * Black            0;30
     * Dark Grey        1;30
     * Red              0;31
     * Light Red        1;31
     * Green            0;32
     * Light Green      1;32
     * Brown            0;33
     * Yellow           1;33
     * Blue             0;34
     * Light Blue       1;34
     * Magenta          0;35
     * Light Magenta    1;35
     * Cyan             0;36
     * Light Cyan       1;36
     * Light Grey       0;37
     * White            1;37
     *
     * ==Background Colors==
     * Black        40
     * Red          41
     * Green        42
     * Yellow       43
     * Blue         44
     * Magenta      45
     * Cyan         46
     * Light Grey   47
     */

    public function success($message): string
    {
        return "\e[0;30;42m{$message}\e[0m\n"; // black in green bg
    }

    public function warning($message): string
    {
        return "\e[0;30;43m{$message}\e[0m\n"; // black in yellow bg
    }

    public function error($message, $fail = false): string
    {
        return "\e[1;37;41m{$message}\e[0m\n"; // white on red bg
    }

    protected function hasParent(): bool
    {
        return get_parent_class($this) !== false;
    }

    protected function getParentOrNull(): ?string
    {
        $parent = get_parent_class($this);
        return $parent !== false ? $parent : null;
    }
}
