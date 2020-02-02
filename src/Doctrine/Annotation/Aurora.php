<?php

namespace Sindla\Bundle\AuroraBundle\Doctrine\Annotation;

/**
 * https://www.doctrine-project.org/projects/doctrine-annotations/en/1.6/custom.html
 *
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