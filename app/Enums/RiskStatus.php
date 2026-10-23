<?php

namespace App\Enums;

enum RiskStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Resolved = 'RESOLVED';
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::InProgress => 'Ditangani',
            self::Resolved => 'Teratasi',
            self::Closed => 'Ditutup',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }
}
