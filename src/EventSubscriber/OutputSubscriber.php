<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

// Symfony
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Aurora
use Sindla\Bundle\AuroraBundle\Utils\Twig\UtilityExtension;

/**
 * https://symfony.com/doc/current/session/locale_sticky_session.html
 *
 * services.yaml:
 *
    Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber:
        arguments:
            $container: '@service_container'
            $utilityExtension: '@aurora.twig.utility'
            $headers:
                text/html:
                    Strict-Transport-Security: "max-age=1536000; includeSubDomains"
                    #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: http:; object-src 'none'"
                    #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'"
                    Content-Security-Policy: "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'"
                    #Content-Security-Policy: "script-src 'nonce-?aurora.nonce?' 'unsafe-inline' 'unsafe-eval' 'strict-dynamic' https: 'self';default-src 'self';"
                    Referrer-Policy: "no-referrer-when-downgrade"
        tags:
            - { name: kernel.event_listener, event: kernel.response }
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

    const PREG_DEV_PREFIX = '/^(stg|staging|dev|develop|test)\./i';
    const PREG_DEV_SUFFIX = '/\.(localhost|local)$/i';

    public function __construct(Container $container, UtilityExtension $utilityExtension, ?array $headers = [])
    {
        /** @var Container Container */
        $this->container = $container;

        /** @var UtilityExtension */
        $this->UtilityExtension = $utilityExtension;

        /** @var array headers */
        $this->headers = $headers;
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

        $pathInfo  = $request->getPathInfo();
        $routeName = $request->attributes->get('_route');

        if (filter_var($this->container->getParameter('aurora.minify.replace'), FILTER_VALIDATE_BOOLEAN)) {
            $response->setContent(
                strtr(
                    $response->getContent(),
                    $this->container->getParameter('aurora.minify.replace.mapper')
                )
            );
        }

        if (
            '/admin/' != substr($pathInfo, 0, 7)
            && !strpos($pathInfo, '_profiler')
            && true == $this->container->getParameter('aurora.minify.output')
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
                !$response->headers->get('X-Do-Not-Minify')
                && !$response->headers->get('x-do-not-minify')
                && !method_exists($response, 'getFile')
                && !in_array($response->headers->get('content-type'), $this->container->getParameter('aurora.minify.output.ignore.content.type'))
            ) {
                $serviceSanitizer = $this->container->get('aurora.sanitizer');
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

        if (!empty($this->headers) && isset($this->headers['text/html']) && in_array($response->headers->get('content-type'), ['', 'text/html', 'text/html; charset=UTF-8'])) {
            foreach ($this->headers['text/html'] as $header => $value) {
                if ('Content-Security-Policy' == $header) {
                    // set CPS header on the response object
                    $response->headers->set("Content-Security-Policy", str_replace('?aurora.nonce?', $this->UtilityExtension->getNonce(), $value));
                } else {
                    $response->headers->set($header, $value);
                }
            }
        }
    }
}
