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

    public function __construct(Container $container, UtilityExtension $utilityExtension, ?array $headers = [])
    {
        /** @var Container Container */
        $this->container = $container;

        /** @var UtilityExtension */
        $this->UtilityExtension = $utilityExtension;

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
        trigger_error('Method ' . __METHOD__ . ' is deprecated. Use Sindla\Bundle\AuroraBundle\EventSubscriber\OutputSubscriber instead.', E_USER_DEPRECATED);
    }
}