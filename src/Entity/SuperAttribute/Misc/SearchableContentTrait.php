<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Sindla\Bundle\AuroraBundle\Doctrine\Attributes\Aurora;
use Sindla\Bundle\AuroraBundle\Doctrine\TypeHint\MetaData;
use Symfony\Component\Serializer\Annotation\Groups;

trait SearchableContentTrait
{
    #[ORM\Column(name: 'searchable_content', type: Types::TEXT, nullable: true, options: ['default' => null, 'comment' => 'Searchable content - aggregated content from multiple fields and tables'])]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $searchableContent = null;

    public function getSearchableContent(): ?string
    {
        return $this->searchableContent;
    }

    public function setSearchableContent(?string $searchableContent): self
    {
        $this->searchableContent = $searchableContent;
        return $this;
    }
}
