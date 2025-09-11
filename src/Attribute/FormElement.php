<?php

namespace Sindla\Bundle\AuroraBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class FormElement
{
    public function __construct(
        public bool $searchable = false,
        public ?string $label = null,
    ) {
    }
}
