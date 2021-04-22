<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

// Symfony
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * https://symfony.com/doc/current/session/locale_sticky_session.html
 *
 * This is not an auto-loaded service
 * You have to create your own class under `src/EventSubscriber/LocaleSubscriber.php`
 *  `class LocaleSubscriber extends \Sindla\Bundle\AuroraBundle\EventSubscriber\LocaleSubscriber implements EventSubscriberInterface`
 * and register it in services.yaml
 *
    App\EventSubscriber\LocaleSubscriber:
        arguments: ['@service_container', '@twig']
        tags:
            - { name: kernel.event_subscriber, event: kernel.request }
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var Container
     */
    protected $container;

    protected $twig;

    public function __construct(Container $container, $twig)
    {
        /** @var Container Container */
        $this->container = $container;

        $this->twig      = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        /**
         * To see other events, run this:
         *  php bin/console debug:event-dispatcher
         *  php bin/console debug:event-dispatcher kernel.request
         *  php bin/console debug:event-dispatcher kernel.response
         */
        return [];
    }

    protected function setLocaleByRouteName(GetResponseEvent $event)
    {
        $request   = $event->getRequest();
        $routeName = $request->get('_route');
        $locales   = $this->container->getParameter('aurora.locales');

        $set = false;
        foreach ($locales as $localeLangCode) {
            // If first two chars of route are the same as locale lang code (eg: route `ro/lorem/ipsum` means the locale is `ro`)
            if (!$set && strtolower($localeLangCode) == strtolower(substr($routeName, -2))) {
                $this->setLocale($localeLangCode, $request);
                $set = true;
            }
        }

        if (!$set) {
            $this->setLocale($this->container->getParameter('aurora.locale'), $request);
        }
    }

    /**
     * Change Locate base on domain TLD
     *
     * @param RequestEvent $event
     */
    protected function setLocateByTLD(RequestEvent $event, array $tldMaps)
    {
        $request = $event->getRequest();

        $domainParts = explode(".", $request->getHost());
        $domainTLD   = '.'. end($domainParts);  // .tld

        $set = false;
        foreach ($tldMaps as $TLD => $locale) {
            if (strtolower($domainTLD) == strtolower($TLD)) {
                $this->setLocale($locale, $request);
                $set = true;
            }
        }

        if (!$set) {
            $this->setLocale($this->container->getParameter('aurora.locale'), $request);
        }
    }

    protected function setLocateBySession(GetResponseEvent $event)
    {

    }

    private function setLocale($locale, $request)
    {
        $locale = strtolower($locale);

        $request->setLocale($locale);
        $this->twig->addGlobal('locale', $locale);
        $request->getSession()->set('locale', $locale);
        $request->getSession()->set('_locale', $locale);
    }
}