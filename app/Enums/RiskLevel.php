<?php

namespace App\Enums;

/**
 * Likelihood (Low to High) and priority (Low to Critical) share one scale; Critical
 * is only valid for priority.
 */
enum RiskLevel: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
    case Critical = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    /**
     * @return list<self>
     */
    public static function likelihoods(): array
    {
        return [self::Low, self::Medium, self::High];
    }
}
