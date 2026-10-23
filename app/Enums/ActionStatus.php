<?php

namespace App\Enums;

enum ActionStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Blocked = 'BLOCKED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::InProgress => 'Dikerjakan',
            self::Blocked => 'Terhambat',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}
