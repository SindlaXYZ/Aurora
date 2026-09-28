<?php

declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\EventSubscriber\LocaleSubscriber;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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

    public function testTheBaseSubscriberListensToNoEvent(): void
    {
        // The application subclass declares the events
        $this->assertSame([], LocaleSubscriber::getSubscribedEvents());
    }

    #[DataProvider('dataSetLocateByTLD')]
    public function testSetLocateByTLD(string $url, string $expectedLocale): void
    {
        $twig    = new Environment(new ArrayLoader());
        $request = Request::create($url);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->createSubscriber($twig)->callSetLocateByTLD($this->createEvent($request), ['.com' => 'en', '.DE' => 'DE']);

        $this->assertSame($expectedLocale, $request->getLocale());
        $this->assertSame($expectedLocale, $request->getSession()->get('locale'));
        $this->assertSame($expectedLocale, $request->getSession()->get('_locale'));
        $this->assertSame($expectedLocale, $twig->getGlobals()['locale']);
    }

    public static function dataSetLocateByTLD(): iterable
    {
        yield '.com' => ['https://www.example.com/page', 'en'];
        yield 'TLD compared case-insensitively, locale lowercased' => ['https://shop.example.de/', 'de'];
        yield 'sub-domain named like a mapped TLD' => ['https://com.example.org/', 'ro'];
        yield 'unmapped TLD falls back to the default locale' => ['https://example.org/', 'ro'];
        yield 'host without TLD' => ['http://localhost/', 'ro'];
    }

    #[DataProvider('dataSetLocaleByMatch')]
    public function testSetLocaleByMatch(string $url, string $expectedLocale): void
    {
        $twig    = new Environment(new ArrayLoader());
        $request = Request::create($url);

        $this->createSubscriber($twig)->callSetLocaleByMatch(
            $this->createEvent($request),
            ['/\.com$/i' => 'en', '/\.com\.localhost$/i' => 'en', '/^de\./i' => 'de']
        );

        $this->assertSame($expectedLocale, $request->getLocale());
        $this->assertSame($expectedLocale, $twig->getGlobals()['locale']);
    }

    public static function dataSetLocaleByMatch(): iterable
    {
        yield 'suffix' => ['https://www.example.com/', 'en'];
        yield 'local development host' => ['http://example.com.localhost/', 'en'];
        yield 'prefix' => ['https://de.example.org/', 'de'];
        yield 'no pattern matches: default locale' => ['https://example.org/', 'ro'];
    }

    public function testSetLocateBySessionChangesNothing(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('addGlobal');

        $request = Request::create('https://www.example.com/');
        $request->setDefaultLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        new LocaleSubscriberMock($this->createContainer(), $twig)->callSetLocateBySession($this->createEvent($request));

        $this->assertSame('fr', $request->getLocale());
        $this->assertSame([], $request->getSession()->all());
    }

    private function createSubscriber(?Environment $twig = null): LocaleSubscriberMock
    {
        return new LocaleSubscriberMock($this->createContainer(), $twig ?? $this->createStub(Environment::class));
    }

    private function createContainer(): Container
    {
        $container = new Container();
        $container->setParameter('aurora.locales', ['en', 'ro']);
        $container->setParameter('aurora.locale', 'ro');

        return $container;
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

    public function callSetLocateByTLD(RequestEvent $event, array $tldMaps): void
    {
        $this->setLocateByTLD($event, $tldMaps);
    }

    public function callSetLocaleByMatch(RequestEvent $event, array $tldMatches): void
    {
        $this->setLocaleByMatch($event, $tldMatches);
    }

    public function callSetLocateBySession(RequestEvent $event): void
    {
        $this->setLocateBySession($event);
    }
}
