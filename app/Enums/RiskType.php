<?php

namespace App\Enums;

enum RiskType: string
{
    case Risk = 'RISK';
    case Issue = 'ISSUE';

    public function label(): string
    {
        return match ($this) {
            self::Risk => 'Risiko',
            self::Issue => 'Isu',
        };
    }
}
