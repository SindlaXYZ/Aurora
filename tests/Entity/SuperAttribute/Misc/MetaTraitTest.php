<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Misc;

use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc\MetaTrait;

class MetaTraitWrapper
{
    use MetaTrait;
}

class MetaTraitTest extends TestCase
{
    private MetaTraitWrapper $metaTrait;

    protected function setUp(): void
    {
        $this->metaTrait = new MetaTraitWrapper();
    }

    public function testGetMetaReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->metaTrait->getMeta());
    }

    public function testSetMetaOverridesCurrentMeta(): void
    {
        $meta = ['title' => 'Aurora'];

        $result = $this->metaTrait->setMeta($meta);

        self::assertSame($this->metaTrait, $result);
        self::assertSame($meta, $this->metaTrait->getMeta());
    }

    public function testAddMetaAppendsValue(): void
    {
        $this->metaTrait->setMeta(['initial']);

        $result = $this->metaTrait->addMeta('appended');

        self::assertSame($this->metaTrait, $result);
        self::assertSame(['initial', 'appended'], $this->metaTrait->getMeta());
    }

    public function testMergeMetaCombinesExistingValues(): void
    {
        $this->metaTrait->setMeta(['title' => 'Aurora']);

        $result = $this->metaTrait->mergeMeta(['description' => 'Bundle']);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            ['title' => 'Aurora', 'description' => 'Bundle'],
            $this->metaTrait->getMeta()
        );
    }

    public function testInjectMetaBehavesLikeMerge(): void
    {
        $this->metaTrait->setMeta(['title' => 'Aurora']);

        $result = $this->metaTrait->injectMeta(['language' => 'PHP']);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            ['title' => 'Aurora', 'language' => 'PHP'],
            $this->metaTrait->getMeta()
        );
    }

    public function testRemoveMetaDeletesExistingValue(): void
    {
        $this->metaTrait->setMeta(['keep', 'remove']);

        $result = $this->metaTrait->removeMeta('remove');

        self::assertSame($this->metaTrait, $result);
        self::assertSame(['keep'], $this->metaTrait->getMeta());
    }

    public function testRemoveMetaIgnoresMissingValue(): void
    {
        $this->metaTrait->setMeta(['keep']);

        $this->metaTrait->removeMeta('missing');

        self::assertSame(['keep'], $this->metaTrait->getMeta());
    }
}
