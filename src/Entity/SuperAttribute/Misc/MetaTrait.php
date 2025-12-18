<?php

namespace Sindla\Bundle\AuroraBundle\Entity\SuperAttribute\Misc;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sindla\Bundle\AuroraBundle\Config\AuroraConstants;
use Sindla\Bundle\AuroraBundle\Doctrine\Attributes\Aurora;
use Sindla\Bundle\AuroraBundle\Doctrine\TypeHint\MetaData;
use Symfony\Component\Serializer\Annotation\Groups;

trait MetaTrait
{
    #[ORM\Column(name: 'meta', type: Types::JSON, nullable: false, options: ['default' => '[]'])]
    #[Aurora(json: true)]
    #[Groups([AuroraConstants::GROUP_READ])]
    private array|MetaData $meta = [];

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function addMeta(mixed $meta): self
    {
        $this->meta[] = $meta;
        return $this;
    }

    public function appendMeta(array $meta): self
    {
        foreach ($meta as $key => $value) {
            if (!isset($this->meta[$key])) {
                // Key doesn't exist, add it with the value
                $this->meta[$key] = $value;
            } else if (is_array($this->meta[$key])) {
                // Key exists and is already an array, append the new value
                $this->meta[$key][] = $value;
            } else {
                // Key exists but is not an array, convert to array and append
                $this->meta[$key] = [$this->meta[$key], $value];
            }
        }

        return $this;
    }

    public function mergeMeta(array $meta): self
    {
        $this->meta = (is_array($this->meta) ? array_merge($this->meta, $meta) : $meta);
        return $this;
    }

    public function injectMeta(mixed $meta): self
    {
        $this->meta = (is_array($this->meta) ? array_merge($this->meta, $meta) : $meta);
        return $this;
    }

    public function removeMeta(mixed $meta): self
    {
        if (true === in_array($meta, $this->meta, true)) {
            $index = array_search($meta, $this->meta);
            array_splice($this->meta, $index, 1);
        }
        return $this;
    }
}
