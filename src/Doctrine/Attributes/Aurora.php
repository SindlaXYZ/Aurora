<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Aurora
{
    public bool   $toSting      = false; // eg: #[Aurora(toSting: true)]
    public bool   $bitwise      = false; // eg: #[Aurora(bitwise: true)]
    public string $bitwiseConst = '';    // eg: #[Aurora(bitwiseConst: "STATUS_")]
    public bool   $json         = false; // eg: #[Aurora(json: true)]

    public function __construct(
        bool   $toSting,
        bool   $bitwise,
        string $bitwiseConst,
        bool   $json
    )
    {
        $this->toSting      = $toSting;
        $this->bitwise      = $bitwise;
        $this->bitwiseConst = $bitwiseConst;
        $this->json         = $json;
    }
}
