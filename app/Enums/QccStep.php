<?php

namespace App\Enums;

/**
 * The eight official QCC steps. Number and name are fixed, never user-editable.
 */
enum QccStep: int
{
    case Theme = 1;
    case Target = 2;
    case PossibleCauses = 3;
    case DominantCauses = 4;
    case ImprovementPlan = 5;
    case Implementation = 6;
    case Evaluation = 7;
    case Standardization = 8;

    public function label(): string
    {
        return match ($this) {
            self::Theme => 'Menentukan Tema Improvement',
            self::Target => 'Menetapkan Target',
            self::PossibleCauses => 'Menemukan Sebab-Sebab yang Mungkin',
            self::DominantCauses => 'Menentukan Sebab-Sebab yang Dominan',
            self::ImprovementPlan => 'Membuat Rencana Perbaikan',
            self::Implementation => 'Implementasi Rencana Perbaikan',
            self::Evaluation => 'Evaluasi Hasil Perbaikan',
            self::Standardization => 'Standarisasi & Rencana Pencegahan',
        };
    }

    /**
     * @return array<int, array{number: int, name: string}>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (self $step): array => ['number' => $step->value, 'name' => $step->label()],
            self::cases(),
        );
    }
}
