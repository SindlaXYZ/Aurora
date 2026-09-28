<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraCookiesExtractor;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraCookiesExtractor\Cookie;

class CookieTest extends TestCase
{
    public function testANewCookieHasNoValue(): void
    {
        $this->assertSame(
            [
                'name'       => null,
                'value'      => null,
                'domain'     => null,
                'path'       => null,
                'expires'    => null,
                'size'       => null,
                'httpOnly'   => null,
                'secure'     => null,
                'sameSite'   => null,
                'priority'   => null,
                'attributes' => null,
            ],
            new Cookie()->toArray()
        );
    }

    public function testTheFluentSettersFeedTheGettersAndToArray(): void
    {
        $expires = new \DateTimeImmutable('2030-01-02 03:04:05', new \DateTimeZone('UTC'));
        $cookie  = new Cookie();

        $this->assertSame(
            $cookie,
            $cookie
                ->setName('session')
                ->setValue('abc123')
                ->setDomain('example.com')
                ->setPath('/account')
                ->setExpires($expires)
                ->setSize(15)
                ->setHttpOnly(true)
                ->setSecure(false)
                ->setSameSite('Strict')
                ->setPriority('High')
                ->setAttributes(['Partitioned' => true])
        );

        $this->assertSame('session', $cookie->getName());
        $this->assertSame('abc123', $cookie->getValue());
        $this->assertSame('example.com', $cookie->getDomain());
        $this->assertSame('/account', $cookie->getPath());
        $this->assertSame($expires, $cookie->getExpires());
        $this->assertSame(15, $cookie->getSize());
        $this->assertTrue($cookie->getHttpOnly());
        $this->assertFalse($cookie->getSecure());
        $this->assertSame('Strict', $cookie->getSameSite());
        $this->assertSame('High', $cookie->getPriority());
        $this->assertSame(['Partitioned' => true], $cookie->getAttributes());

        $this->assertSame(
            [
                'name'       => 'session',
                'value'      => 'abc123',
                'domain'     => 'example.com',
                'path'       => '/account',
                'expires'    => $expires,
                'size'       => 15,
                'httpOnly'   => true,
                'secure'     => false,
                'sameSite'   => 'Strict',
                'priority'   => 'High',
                'attributes' => ['Partitioned' => true],
            ],
            $cookie->toArray()
        );
    }

    public function testTheSettersAcceptNullToClearAValue(): void
    {
        $cookie = new Cookie()
            ->setDomain('example.com')
            ->setPath('/')
            ->setSize(3)
            ->setHttpOnly(true)
            ->setSecure(true)
            ->setSameSite('Lax')
            ->setPriority('Low');

        $cookie
            ->setDomain(null)
            ->setPath(null)
            ->setSize(null)
            ->setHttpOnly(null)
            ->setSecure(null)
            ->setSameSite(null)
            ->setPriority(null);

        $this->assertSame(array_fill_keys(array_keys($cookie->toArray()), null), $cookie->toArray());
    }
}
