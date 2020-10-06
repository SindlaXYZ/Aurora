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
    public bool $toSting = false; // eg: @Aurora(toSting=true)

    /** @var boolean */
    public bool $bitwise = false;

    /** @var bool */
    public bool $json = false;
}