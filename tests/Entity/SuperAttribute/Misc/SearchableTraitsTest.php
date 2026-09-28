<?php
declare(strict_types=1);

namespace Sindla\Bundle\AuroraBundle\Tests\Entity\SuperAttribute\Misc;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc\SearchableContentTrait;
use Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc\SearchableTrait;

/**
 * SearchableTrait and SearchableContentTrait: texts aggregated from several fields and tables, used together by an entity
 */
class SearchableTraitsTest extends TestCase
{
    public function testTheSearchableTextsAreIndependent(): void
    {
        $entity = new class {
            use SearchableTrait;
            use SearchableContentTrait;
        };

        $this->assertNull($entity->getSearchable());
        $this->assertNull($entity->getSearchableContent());

        $this->assertSame($entity, $entity->setSearchable('aurora bundle symfony'));
        $this->assertSame($entity, $entity->setSearchableContent('Aurora is a Symfony bundle.'));
        $this->assertSame('aurora bundle symfony', $entity->getSearchable());
        $this->assertSame('Aurora is a Symfony bundle.', $entity->getSearchableContent());

        $entity->setSearchable(null);
        $this->assertNull($entity->getSearchable());
        $this->assertSame('Aurora is a Symfony bundle.', $entity->getSearchableContent());

        $entity->setSearchableContent(null);
        $this->assertNull($entity->getSearchableContent());
    }

    public function testTheSearchableTextsAreNullableTextColumns(): void
    {
        $entity = new class {
            use SearchableTrait;
            use SearchableContentTrait;
        };

        $columns = [];
        foreach (['searchable', 'searchableContent'] as $property) {
            $column    = new \ReflectionProperty($entity, $property)->getAttributes(ORM\Column::class)[0]->newInstance();
            $columns[] = [$column->name, $column->type, $column->nullable];
        }

        // The aggregated texts have no length limit
        $this->assertSame([['searchable', Types::TEXT, true], ['searchable_content', Types::TEXT, true]], $columns);
    }
}
