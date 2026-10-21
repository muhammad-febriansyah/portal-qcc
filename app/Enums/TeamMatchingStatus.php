<?php

namespace App\Enums;

enum TeamMatchingStatus: string
{
    case Queued = 'QUEUED';
    case Processing = 'PROCESSING';
    case Ready = 'READY';
    case Failed = 'FAILED';

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu antrean',
            self::Processing => 'Sedang diproses',
            self::Ready => 'Rekomendasi siap',
            self::Failed => 'Gagal',
        };
    }
}
