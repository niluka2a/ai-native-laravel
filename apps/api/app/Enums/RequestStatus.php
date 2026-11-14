<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    /** Human-readable name shown to staff. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
        };
    }
}
