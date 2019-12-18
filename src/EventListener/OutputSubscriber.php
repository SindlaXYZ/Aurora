<?php

namespace Sindla\Bundle\AuroraBundle\EventListener;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * https://symfony.com/doc/current/session/locale_sticky_session.html
 */
class OutputSubscriber implements EventSubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        /** @var Container Container */
        $this->container = $container;
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
        if (true == $this->container->getParameter('aurora.minify.output') && 0 == count(array_filter($this->container->getParameter('aurora.minify.output.ignore.extensions'), function ($extension) use ($pathInfo) {
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
    }
}