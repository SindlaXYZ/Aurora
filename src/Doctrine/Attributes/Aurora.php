<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Aurora
{
    public function __construct(
        bool    $toSting = false,       // eg: #[Aurora(toSting: true)]
        ?string $bitwiseConst = null,   // eg: #[Aurora(bitwiseConst: "STATUS_")]
        bool    $json = false           // eg: #[Aurora(json: true)]
    )
    {
    }
}
