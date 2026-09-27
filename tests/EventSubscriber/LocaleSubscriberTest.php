<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\LocaleSubscriber;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

class LocaleSubscriberTest extends TestCase
{
    public function testSetLocaleByRouteName(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'en_homepage');
        $request->setSession(new Session(new MockArraySessionStorage()));

        // Used to be type-hinted with GetResponseEvent (removed in Symfony 5): a RequestEvent was a TypeError
        $this->createSubscriber()->callSetLocaleByRouteName($this->createEvent($request));

        $this->assertSame('en', $request->getLocale());
        $this->assertSame('en', $request->getSession()->get('_locale'));
    }

    public function testFallsBackToTheDefaultLocaleWithoutRouteAndSession(): void
    {
        // No "_route" attribute (e.g. 404) and no session (stateless request)
        $request = Request::create('/');

        $this->createSubscriber()->callSetLocaleByRouteName($this->createEvent($request));

        $this->assertSame('ro', $request->getLocale());
    }

    private function createSubscriber(): LocaleSubscriberMock
    {
        $container = new Container();
        $container->setParameter('aurora.locales', ['en', 'ro']);
        $container->setParameter('aurora.locale', 'ro');

        return new LocaleSubscriberMock($container, $this->createStub(Environment::class));
    }

    private function createEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}

class LocaleSubscriberMock extends LocaleSubscriber
{
    public function callSetLocaleByRouteName(RequestEvent $event): void
    {
        $this->setLocaleByRouteName($event);
    }
}
