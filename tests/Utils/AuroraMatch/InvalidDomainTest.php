<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Utils\AuroraMatch;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Utils\AuroraMatch\AuroraMatch;

class InvalidDomainTest extends TestCase
{
    public function testInvalidUrlInputsDoNotTriggerWarnings(): void
    {
        $matcher = new AuroraMatch();

        $this->assertFalse($matcher->matchDomain(':::', 'example.com'));
        $this->assertFalse($matcher->matchDomain('example.com', ':::'));
    }
}
