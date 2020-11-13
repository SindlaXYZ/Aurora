<?php

namespace Sindla\Bundle\AuroraBundle\EventListener;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use Sindla\Bundle\AuroraBundle\Utils\Twig\UtilityExtension;

/**
 * https://symfony.com/doc/current/session/locale_sticky_session.html
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

    public function __construct(Container $container, UtilityExtension $UtilityExtension, ?array $headers = [])
    {
        /** @var Container Container */
        $this->container = $container;

        /** @var UtilityExtension */
        $this->UtilityExtension = $UtilityExtension;

        /** @var array headers */
        $this->headers = $headers;
    }

    public static function getSubscribedEvents()
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
        $pathInfo = $event->getRequest()->getPathInfo();

        if ('/admin/' != substr($pathInfo, 0, 7) && !strpos($pathInfo, '_profiler') && true == $this->container->getParameter('aurora.minify.output') && 0 == count(array_filter($this->container->getParameter('aurora.minify.output.ignore.extensions'), function ($extension) use ($pathInfo) {
                // If extensions found in path info
                if (substr_compare($pathInfo, $extension, strlen($pathInfo) - strlen($extension), strlen($extension)) === 0) {
                    return $extension;
                }
            }))) {

            $response = $event->getResponse();
            if (!$response->headers->get('X-Do-Not-Minify') && !$response->headers->get('x-do-not-minify') && !method_exists($response, 'getFile') && !in_array($response->headers->get('content-type'), $this->container->getParameter('aurora.minify.output.ignore.content.type'))) {
                $serviceSanitizer = $this->container->get('aurora.sanitizer');
                $response->setContent($serviceSanitizer->minifyHTML($response->getContent()));
            }
        }

        if (preg_match(self::PREG_DEV_PREFIX, $event->getRequest()->getHost()) || preg_match(self::PREG_DEV_SUFFIX, $event->getRequest()->getHost())) {
            if (!isset($response)) {
                $response = $event->getResponse();
            }
            $response->headers->set('X-Robots-Tag', 'none');
        }

        if (!empty($this->headers) && isset($this->headers['text/html']) && in_array($response->headers->get('content-type'), ['', 'text/html'])) {
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