<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Sindla\Bundle\AuroraBundle\Utils\AuroraSanitizer\AuroraSanitizer;
use Sindla\Bundle\AuroraBundle\Utils\AuroraTwig\UtilityExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * https://symfony.com/doc/current/session/locale_sticky_session.html
 *
 * services.yaml:
 *
 * Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber:
 * arguments:
 * $container: '@service_container'
 * $utilityExtension: '@aurora.twig.utility'
 * $headers:
 * text/html:
 * Strict-Transport-Security: "max-age=1536000; includeSubDomains"
 * #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: http:; object-src 'none'"
 * #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'"
 * Content-Security-Policy: "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'"
 * #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: 'self';default-src 'self';"
 * Referrer-Policy: "no-referrer-when-downgrade"
 * tags: [kernel.event_subscriber]
 */
class OutputSubscriber implements EventSubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /** @var UtilityExtension */
    private $UtilityExtension;

    /** @var array */
    private $headers;

    /**
     * The response handled for each request
     *
     * @var \WeakMap<Request, Response>
     */
    private \WeakMap $handledResponses;

    const PREG_DEV_PREFIX = '/^(stg|staging|dev|develop|test)\./i';
    const PREG_DEV_SUFFIX = '/\.(localhost|local)$/i';

    public function __construct(Container $container, UtilityExtension $utilityExtension, ?array $headers = [])
    {
        $this->container        = $container;
        $this->UtilityExtension = $utilityExtension;
        $this->headers          = $headers;
        $this->handledResponses = new \WeakMap();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must be registered before (i.e. with a higher priority than) the default Locale listener
            KernelEvents::RESPONSE => [['onKernelResponse', 20]],
        ];
    }

    /**
     * @param ResponseEvent $event
     *
     * @throws \Exception
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        /** @var Request $request */
        $request = $event->getRequest();

        /** @var Response $response */
        $response = $event->getResponse();

        // Once per response: registered as a "kernel.event_listener" (as the README used to say) and as an event subscriber (autoconfigure),
        // it ran twice, e.g. "aurora.minify.replace.mapper" was applied twice ("/static/" => "https://cdn.example.com/static/" became
        // "https://cdn.example.comhttps://cdn.example.com/static/")
        if (($this->handledResponses[$request] ?? null) === $response) {
            return;
        }

        $this->handledResponses[$request] = $response;

        $pathInfo  = $request->getPathInfo();
        $routeName = $request->attributes->get('_route');

        // Streamed (StreamedResponse, StreamedJsonResponse) and file (BinaryFileResponse) responses have no content (false):
        // strtr(false) is a TypeError and setContent() throws a LogicException on them
        $hasContent = false !== $response->getContent();

        if (
            $hasContent
            && !$response->headers->get('X-Do-Not-Minify')
            && !$response->headers->get('x-do-not-minify')
            && !method_exists($response, 'getFile')
            && !$this->isIgnoredContentType($response)
        ) {
            if (
                $this->container->hasParameter('aurora.minify.replace')
                && filter_var($this->container->getParameter('aurora.minify.replace'), FILTER_VALIDATE_BOOLEAN)
            ) {
                $response->setContent(
                    strtr(
                        $response->getContent(),
                        $this->container->getParameter('aurora.minify.replace.mapper')
                    )
                );
            }
        }

        if (
            '/admin/' != substr($pathInfo, 0, 7)
            && !strpos($pathInfo, '_profiler')
            && filter_var($this->container->getParameter('aurora.minify.output'), FILTER_VALIDATE_BOOLEAN)
            && 0 == count(
                array_filter($this->container->getParameter('aurora.minify.output.ignore.extensions'), function ($extension) use ($pathInfo) {
                    // If extensions found in path info
                    if (substr_compare($pathInfo, $extension, strlen($pathInfo) - strlen($extension), strlen($extension)) === 0) {
                        return $extension;
                    }
                })
            )
        ) {
            if (
                $hasContent
                && !$response->headers->get('X-Do-Not-Minify')
                && !$response->headers->get('x-do-not-minify')
                && !method_exists($response, 'getFile')
                && !$this->isIgnoredContentType($response)
                // HTML minifier: other content types (e.g. JSON) would be corrupted (whitespaces inside strings are collapsed)
                && in_array($this->mediaType($response), ['', 'text/html'], true)
            ) {
                // The "aurora.sanitizer" service is optional (it is no longer registered by the bundle)
                $serviceSanitizer = $this->container->has('aurora.sanitizer') ? $this->container->get('aurora.sanitizer') : null;
                if (!$serviceSanitizer instanceof AuroraSanitizer) {
                    $serviceSanitizer = new AuroraSanitizer();
                }

                $response->setContent($serviceSanitizer->minifyHTML($response->getContent()));
            }
        }

        // Protect against boots
        if (
            '/xhr' == substr($pathInfo, 0, 4)
            || ($routeName && 'XHR' == substr($routeName, 0, 3))
            || preg_match(self::PREG_DEV_PREFIX, $request->getHost())
            || preg_match(self::PREG_DEV_SUFFIX, $request->getHost())
        ) {
            $response->headers->set('X-Robots-Tag', 'none'); // none - Equivalent to noindex, nofollow
        }

        // Compared by media type: "text/html; charset=utf-8" or "text/html;charset=UTF-8" used to get no security header (CSP, HSTS, ...)
        if (!empty($this->headers) && isset($this->headers['text/html']) && in_array($this->mediaType($response), ['', 'text/html'], true)) {
            foreach ($this->headers['text/html'] as $header => $value) {
                // A header set by the controller, e.g. a stricter Content-Security-Policy, is kept: it used to be replaced by the default
                if ($response->headers->has($header)) {
                    continue;
                }

                if ('Content-Security-Policy' == $header) {
                    // set CPS header on the response object
                    $response->headers->set("Content-Security-Policy", str_replace('?aurora.nonce?', $this->UtilityExtension->getNonce(), $value));
                } else {
                    $response->headers->set($header, $value);
                }
            }
        }
    }

    /**
     * The media type (lowercase, without parameters): "text/csv; charset=UTF-8" => "text/csv"; "" when the header is not set yet
     */
    private function mediaType(Response $response): string
    {
        return strtolower(trim(explode(';', (string)$response->headers->get('Content-Type'))[0]));
    }

    private function isIgnoredContentType(Response $response): bool
    {
        $ignored = $this->container->getParameter('aurora.minify.output.ignore.content.type');

        return in_array($response->headers->get('Content-Type'), $ignored, true) || in_array($this->mediaType($response), $ignored, true);
    }
}
