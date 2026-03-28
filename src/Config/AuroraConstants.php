<?php

namespace Sindla\Bundle\AuroraBundle\Config;

class AuroraConstants
{
    final public const string GROUP_READ                               = 'Aurora:Read';
    final public const string GROUP_READ_IDENTIFIABLE                  = 'Aurora:Read:Identifiable';
    final public const string GROUP_READ_TIMESTAMPABLE                 = 'Aurora:Read:Timestampable';
    final public const string GROUP_WRITE                              = 'Aurora:Write';
    final public const string TIMESTAMPABLE_DELETED_DEFAULT_DELETED_AT = '2222-02-22 22:22:22.222222';
}
