<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Aurora
{
    public bool    $toSting      = false;   // eg: #[Aurora(toSting: true)]
    public ?string $bitwiseConst = null;    // eg: #[Aurora(bitwiseConst: "STATUS_")]
    public bool    $json         = false;   // eg: #[Aurora(json: true)]

    public function __construct(
        bool   $toSting = false,
        string $bitwiseConst = null,
        bool   $json = false
    )
    {
        $this->toSting      = $toSting;
        $this->bitwiseConst = $bitwiseConst;
        $this->json         = $json;
    }
}
