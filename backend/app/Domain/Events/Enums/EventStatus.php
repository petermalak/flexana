<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function isLive(): bool
    {
        return in_array($this, [self::Scheduled, self::Published], true);
    }
}

