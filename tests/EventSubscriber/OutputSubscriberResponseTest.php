<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber;
use Sindla\Bundle\AuroraBundle\Utils\AuroraHelper\AuroraHelper;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

/**
 * clear; php vendor/phpunit/phpunit.phar -c phpunit.xml.dist vendor/sindla/aurora/tests/EventSubscriber/OutputSubscriberResponseTest.php --no-coverage
 */
class OutputSubscriberResponseTest extends TestCase
{
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

    /**
     * @param array<string, mixed> $parameters Parameters to override; a null value removes the parameter
     */
    private function dispatch(Response $response, array $parameters = []): Response
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
            new UtilityExtension($container, new RequestStack(), $this->createStub(Environment::class), new AuroraHelper())
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
