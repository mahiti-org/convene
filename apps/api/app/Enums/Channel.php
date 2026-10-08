<?php

namespace App\Enums;

enum Channel: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Any = 'any';

    /** Whether a permission granted on this channel covers a request made on $requested. */
    public function covers(self $requested): bool
    {
        return $this === self::Any || $this === $requested;
    }
}
