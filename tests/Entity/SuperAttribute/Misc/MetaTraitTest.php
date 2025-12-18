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

    ###############################################################################################
    ###   Tests for appendMeta()   ################################################################

    public function testAppendMetaAddsNewKey(): void
    {
        $this->metaTrait->setMeta(['existing' => 'value']);

        $result = $this->metaTrait->appendMeta(['new' => 'data']);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            ['existing' => 'value', 'new' => 'data'],
            $this->metaTrait->getMeta()
        );
    }

    public function testAppendMetaConvertsValueToArrayWhenKeyExists(): void
    {
        $this->metaTrait->setMeta(['test' => 123]);

        $result = $this->metaTrait->appendMeta(['test' => 1234]);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            ['test' => [123, 1234]],
            $this->metaTrait->getMeta()
        );
    }

    public function testAppendMetaAppendsToExistingArray(): void
    {
        $this->metaTrait->setMeta(['test' => [123, 456]]);

        $result = $this->metaTrait->appendMeta(['test' => 789]);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            ['test' => [123, 456, 789]],
            $this->metaTrait->getMeta()
        );
    }

    public function testAppendMetaHandlesMultipleKeys(): void
    {
        $this->metaTrait->setMeta(['key1' => 'value1']);

        $result = $this->metaTrait->appendMeta([
            'key1' => 'value2',
            'key2' => 'value3',
            'key3' => 'value4'
        ]);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(
            [
                'key1' => ['value1', 'value2'],
                'key2' => 'value3',
                'key3' => 'value4'
            ],
            $this->metaTrait->getMeta()
        );
    }

    public function testAppendMetaWorksWithEmptyMeta(): void
    {
        $result = $this->metaTrait->appendMeta(['test' => 123]);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(['test' => 123], $this->metaTrait->getMeta());
    }

    public function testAppendMetaWithEmptyArray(): void
    {
        $this->metaTrait->setMeta(['existing' => 'value']);

        $result = $this->metaTrait->appendMeta([]);

        self::assertSame($this->metaTrait, $result);
        self::assertSame(['existing' => 'value'], $this->metaTrait->getMeta());
    }
}
