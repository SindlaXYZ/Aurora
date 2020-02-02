<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Aurora
{
    /** @var boolean */
    public $toSting;

    /** @var boolean */
    public $bitwise;
}