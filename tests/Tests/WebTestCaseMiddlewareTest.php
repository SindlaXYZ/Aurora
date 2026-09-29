<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Tests\WebTestCaseMiddleware;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class WebTestCaseMiddlewareTest extends TestCase
{
    /**
     * @var resource
     */
    private $stderrFilter;

    protected function setUp(): void
    {
        if (!in_array(WebTestCaseMiddlewareTestStderrFilter::NAME, stream_get_filters(), true)) {
            stream_filter_register(WebTestCaseMiddlewareTestStderrFilter::NAME, WebTestCaseMiddlewareTestStderrFilter::class);
        }

        // The progress is written to STDERR: captured
        WebTestCaseMiddlewareTestStderrFilter::$buffer = '';
        $this->stderrFilter = stream_filter_append(STDERR, WebTestCaseMiddlewareTestStderrFilter::NAME, STREAM_FILTER_WRITE);
    }

    protected function tearDown(): void
    {
        stream_filter_remove($this->stderrFilter);
    }

    public function testProgressPrintsADotPerTestAndTheCounterAtTheEnd(): void
    {
        $output = $this->progress(new class ('progress') extends WebTestCaseMiddleware {}, 3);

        $this->assertSame(
            sprintf("\nRun %s() tests ...\n... %s3/3\n", WebTestCaseMiddleware::class, str_repeat(' ', 60)),
            $output
        );
    }

    public function testProgressBreaksTheLineEvery63Tests(): void
    {
        $lines = explode("\n", $this->progress(new class ('progress') extends WebTestCaseMiddleware {}, 64));

        $this->assertSame(sprintf('%s 63/64', str_repeat('.', 63)), $lines[2]);
        // The counter of the last, shorter line is aligned with the counters of the full lines
        $this->assertSame(sprintf('. %s64/64', str_repeat(' ', 62)), $lines[3]);
        $this->assertSame(strlen($lines[2]), strlen($lines[3]));
        $this->assertSame('', $lines[4]);
    }

    public function testProgressStartRestartsTheCounter(): void
    {
        $testCase = new class ('progress') extends WebTestCaseMiddleware {};
        $this->progress($testCase, 2);
        WebTestCaseMiddlewareTestStderrFilter::$buffer = '';

        $testCase->progressStart(1);
        $testCase->progressAdvance();

        $this->assertSame(sprintf("\nRun %s() tests ...\n. %s1/1\n", WebTestCaseMiddleware::class, str_repeat(' ', 62)), WebTestCaseMiddlewareTestStderrFilter::$buffer);
    }

    #[DataProvider('dataColoredMessages')]
    public function testColoredMessages(string $method, string $expected): void
    {
        $this->assertSame($expected, new WebTestCaseMiddleware('messages')->{$method}('Done'));
    }

    public static function dataColoredMessages(): iterable
    {
        yield 'success: black on green' => ['success', "\e[0;30;42mDone\e[0m\n"];
        yield 'warning: black on yellow' => ['warning', "\e[0;30;43mDone\e[0m\n"];
        yield 'error: white on red' => ['error', "\e[1;37;41mDone\e[0m\n"];
    }

    public function testTheParentIsTheParentOfTheRuntimeClass(): void
    {
        $hasParent       = new \ReflectionMethod(WebTestCaseMiddleware::class, 'hasParent');
        $getParentOrNull = new \ReflectionMethod(WebTestCaseMiddleware::class, 'getParentOrNull');
        $middleware      = new WebTestCaseMiddleware('parent');
        $testCase        = new class ('parent') extends WebTestCaseMiddleware {};

        $this->assertTrue($hasParent->invoke($middleware));
        $this->assertSame(WebTestCase::class, $getParentOrNull->invoke($middleware));
        $this->assertSame(WebTestCaseMiddleware::class, $getParentOrNull->invoke($testCase));
    }

    #[RequiresMethod(UserPasswordHasher::class, 'isPasswordValid')]
    public function testLoginWithRawPasswordDoesNotLogInWithAWrongPassword(): void
    {
        $tokenStorage = new TokenStorage();

        $this->login($tokenStorage, new InMemoryUser('user', 'your-password-here', ['ROLE_USER']), 'a-wrong-password');

        $this->assertNull($tokenStorage->getToken());
    }

    #[RequiresMethod(UserPasswordHasher::class, 'isPasswordValid')]
    public function testLoginWithRawPasswordLogsInWithTheRightPassword(): void
    {
        $tokenStorage = new TokenStorage();
        $user         = new InMemoryUser('user', 'your-password-here', ['ROLE_USER', 'ROLE_ADMIN']);

        // The Symfony 5 UsernamePasswordToken constructor ($user, $credentials, $firewallName, $roles) used to be a TypeError
        $this->login($tokenStorage, $user, 'your-password-here');

        $token = $tokenStorage->getToken();

        $this->assertInstanceOf(UsernamePasswordToken::class, $token);
        $this->assertSame($user, $token->getUser());
        $this->assertSame('database', $token->getFirewallName());
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $token->getRoleNames());
    }

    /**
     * Logs the user in with loginWithRawPassword() (which calls loginWithoutValidation()) in a test case that uses the $tokenStorage
     */
    private function login(TokenStorage $tokenStorage, InMemoryUser $user, string $rawPassword): void
    {
        $container = new Container();
        $container->set(UserPasswordHasherInterface::class, new UserPasswordHasher(new PasswordHasherFactory([InMemoryUser::class => ['algorithm' => 'plaintext']])));
        $container->set('security.token_storage', $tokenStorage);

        $testCase = new class ('login') extends WebTestCaseMiddleware {
            public static ?Container $testContainer = null;

            public function login(InMemoryUser $user, string $rawPassword): void
            {
                $this->loginWithRawPassword($user, $rawPassword);
            }

            protected static function getContainer(): Container
            {
                return self::$testContainer;
            }
        };
        $testCase::$testContainer = $container;

        $testCase->login($user, $rawPassword);
    }

    /**
     * Runs a progress of $count tests and returns what was written to STDERR
     */
    private function progress(WebTestCaseMiddleware $testCase, int $count): string
    {
        $testCase->progressStart($count);

        for ($index = 0; $index < $count; $index++) {
            $testCase->progressAdvance();
        }

        return WebTestCaseMiddlewareTestStderrFilter::$buffer;
    }
}

final class WebTestCaseMiddlewareTestStderrFilter extends \php_user_filter
{
    public const string NAME = 'aurora.web_test_case_middleware_test.stderr';

    public static string $buffer = '';

    /**
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        // Captured, not passed on: nothing reaches the real STDERR
        while ($bucket = stream_bucket_make_writeable($in)) {
            self::$buffer .= $bucket->data;
            $consumed     += $bucket->datalen;
        }

        return PSFS_PASS_ON;
    }
}
