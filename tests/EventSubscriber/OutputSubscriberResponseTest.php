<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/EventSubscriber/OutputSubscriberResponseTest.php --no-coverage
 */
class OutputSubscriberResponseTest extends TestCase
{
    private const array SECURITY_HEADERS = [
        'text/html' => [
            'Strict-Transport-Security' => 'max-age=1536000; includeSubDomains',
            'Content-Security-Policy'   => "script-src 'nonce-?aurora.nonce?'; object-src 'none'",
            'Referrer-Policy'           => 'no-referrer-when-downgrade',
        ],
    ];

    public function testHtmlResponseIsMinified(): void
    {
        $response = $this->dispatch(new Response("<div>\n    <p>Aurora</p>\n</div>"), ['aurora.minify.replace' => false]);

        $this->assertSame('<div><p>Aurora</p></div>', $response->getContent());
    }

    public function testStreamedResponseIsLeftUntouched(): void
    {
        // getContent() is false for streamed responses: strtr(false) was a TypeError, setContent() throws a LogicException
        $response = $this->dispatch(new StreamedResponse(static function (): void {
            echo 'Aurora';
        }));

        $this->assertFalse($response->getContent());
    }

    public function testJsonResponseIsNotMinified(): void
    {
        $response = new JsonResponse(['text' => 'Aurora  bundle']);
        $content  = $response->getContent();

        // The HTML minifier used to collapse the whitespaces inside the JSON strings
        $this->assertSame($content, $this->dispatch($response, ['aurora.minify.replace' => false])->getContent());
    }

    public function testIgnoredContentTypeWithCharsetIsNotReplaced(): void
    {
        $response = $this->dispatch(new Response('Aurora', Response::HTTP_OK, ['Content-Type' => 'text/csv; charset=UTF-8']));

        $this->assertSame('Aurora', $response->getContent());
    }

    public function testReplaceMapperIsApplied(): void
    {
        $response = $this->dispatch(new Response('<p>Aurora</p>'));

        $this->assertSame('<p>Sindla</p>', $response->getContent());
    }

    public function testReplaceParametersAreOptional(): void
    {
        $response = $this->dispatch(new Response('<p>Aurora</p>'), [
            'aurora.minify.replace'        => null,
            'aurora.minify.replace.mapper' => null,
        ]);

        $this->assertSame('<p>Aurora</p>', $response->getContent());
    }

    #[DataProvider('dataHtmlContentTypes')]
    public function testSecurityHeadersAreAddedToEveryHtmlResponse(?string $contentType): void
    {
        $headers  = null === $contentType ? [] : ['Content-Type' => $contentType];
        $response = $this->dispatch(new Response('<p>Aurora</p>', Response::HTTP_OK, $headers), [], self::SECURITY_HEADERS);

        // Only "text/html; charset=UTF-8" (exact case) used to get them: "text/html; charset=utf-8" had no CSP and no HSTS
        $this->assertSame('max-age=1536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
        $this->assertMatchesRegularExpression("/^script-src 'nonce-[A-Za-z0-9+\\/=]+'; object-src 'none'$/", (string)$response->headers->get('Content-Security-Policy'));
        $this->assertSame('no-referrer-when-downgrade', $response->headers->get('Referrer-Policy'));
    }

    public static function dataHtmlContentTypes(): iterable
    {
        yield 'not set yet' => [null];
        yield 'text/html' => ['text/html'];
        yield 'UTF-8' => ['text/html; charset=UTF-8'];
        yield 'lowercase charset' => ['text/html; charset=utf-8'];
        yield 'no space' => ['text/html;charset=UTF-8'];
    }

    public function testSecurityHeadersAreNotAddedToOtherResponses(): void
    {
        $response = $this->dispatch(new JsonResponse(['text' => 'Aurora']), [], self::SECURITY_HEADERS);

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function testASecurityHeaderSetByTheControllerIsKept(): void
    {
        $response = new Response('<p>Aurora</p>', Response::HTTP_OK, ['Content-Security-Policy' => "default-src 'none'"]);
        $response = $this->dispatch($response, [], self::SECURITY_HEADERS);

        // The stricter policy of the controller used to be replaced by the default one
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('max-age=1536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    /**
     * Registered as a "kernel.event_listener" (as the README used to say) and as an event subscriber (autoconfigure), it ran twice: the
     * replacements were applied twice
     */
    public function testTheResponseIsHandledOnceWhenTheSubscriberIsRegisteredTwice(): void
    {
        $container = new Container();
        $container->setParameter('aurora.minify.output', true);
        $container->setParameter('aurora.minify.output.ignore.extensions', []);
        $container->setParameter('aurora.minify.output.ignore.content.type', []);
        $container->setParameter('aurora.minify.replace', true);
        $container->setParameter('aurora.minify.replace.mapper', ['/static/' => 'https://cdn.example.com/static/']);

        $subscriber = new OutputSubscriber(
            $container,
            new UtilityExtension($container, new RequestStack(), $this->createStub(Environment::class), new AuroraHelper()),
            []
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);
        $dispatcher->addListener(KernelEvents::RESPONSE, [$subscriber, 'onKernelResponse']);

        $kernel   = $this->createStub(HttpKernelInterface::class);
        $response = new Response();

        foreach (['/page', '/other-page'] as $path) {
            // The same response object for every request (e.g. kept by the controller in a long-running worker): handled once per request
            $response->setContent('<img src="/static/a.png">');

            $event = new ResponseEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST, $response);
            $dispatcher->dispatch($event, KernelEvents::RESPONSE);

            $this->assertSame('<img src="https://cdn.example.com/static/a.png">', $event->getResponse()->getContent(), $path);
        }
    }

    /**
     * @param array<string, mixed>                       $parameters Parameters to override; a null value removes the parameter
     * @param array<string, array<string, string>>|null $headers
     */
    private function dispatch(Response $response, array $parameters = [], ?array $headers = []): Response
    {
        $parameters += [
            'aurora.minify.output'                     => true,
            'aurora.minify.output.ignore.extensions'   => ['.pdf', '.csv'],
            'aurora.minify.output.ignore.content.type' => ['text/plain', 'text/csv'],
            'aurora.minify.replace'                    => true,
            'aurora.minify.replace.mapper'             => ['Aurora' => 'Sindla'],
        ];

        $container = new Container();
        foreach ($parameters as $name => $value) {
            if (null !== $value) {
                $container->setParameter($name, $value);
            }
        }

        $subscriber = new OutputSubscriber(
            $container,
            new UtilityExtension($container, new RequestStack(), $this->createStub(Environment::class), new AuroraHelper()),
            $headers
        );

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('https://app.example/page'),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        return $event->getResponse();
    }
}
