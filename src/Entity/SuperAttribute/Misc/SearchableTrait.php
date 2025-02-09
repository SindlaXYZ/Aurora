<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Sindla\Bundle\AuroraBundle\Doctrine\Attributes\Aurora;
use Sindla\Bundle\AuroraBundle\Doctrine\TypeHint\MetaData;
use Symfony\Component\Serializer\Annotation\Groups;

trait SearchableTrait
{
    #[ORM\Column(name: 'searchable', type: Types::TEXT, nullable: true, options: ['default' => null, 'comment' => 'Searchable - aggregated texts from multiple fields and tables'])]
    #[Groups([AuroraConstants::GROUP_READ])]
    private ?string $searchable = null;

    public function getSearchable(): ?string
    {
        return $this->searchable;
    }

    public function setSearchable(?string $searchable): self
    {
        $this->searchable = $searchable;
        return $this;
    }
}
