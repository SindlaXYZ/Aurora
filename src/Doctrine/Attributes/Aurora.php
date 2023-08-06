<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Attributes;

use Attribute;

#[Attribute]
class Aurora
{
    public bool   $toSting      = false; // eg: #[Aurora(toSting: true)]
    public bool   $bitwise      = false; // eg: #[Aurora(bitwise: true)]
    public string $bitwiseConst = '';    // eg: #[Aurora(bitwiseConst: "STATUS_")]
    public bool   $json         = false; // eg: #[Aurora(json: true)]
}
