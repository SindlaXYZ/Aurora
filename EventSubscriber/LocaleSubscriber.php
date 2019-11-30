<?php

namespace Sindla\Bundle\AuroraBundle\EventSubscriber;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * http://symfony.com/doc/3.4/session/locale_sticky_session.html
 *
 * This is not an autoloaded service
 * You have to create your own class under `src/Bundle/EventSubscriber/LocaleSubscriber.php`
 *  `class LocaleSubscriber extends \Sindla\Bundle\SindlfonyBundle\EventSubscriber\LocaleSubscriber implements EventSubscriberInterface`
 */
class LocaleSubscriber
{
    protected function setLocaleByRouteName(GetResponseEvent $event)
    {
        $request   = $event->getRequest();
        $routeName = $request->get('_route');
        $locales   = $this->container->getParameter('aurora')['locales'];

        $set = false;
        foreach ($locales as $localeLangCode) {
            // If first two chars of route are the same as locale lang code (eg: route `ro/lorem/ipsum` means the locale is `ro`)
            if (!$set && strtolower($localeLangCode) == strtolower(substr($routeName, -2))) {
                $this->setLocale($localeLangCode, $request);
                $set = true;
            }
        }

        if (!$set) {
            $this->setLocale($this->container->getParameter('aurora')['locale'], $request);
        }
    }

    /**
     * Change Locate base on domain TLD
     *
     * @param GetResponseEvent $event
     */
    protected function setLocateByTLD(GetResponseEvent $event, array $tldMaps)
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
            $this->setLocale($this->container->getParameter('aurora')['locale'], $request);
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