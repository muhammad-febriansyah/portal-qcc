<?php

namespace App\Enums;

/**
 * The kind of improvement a proposal aims at, used to group proposals on the program dashboard.
 */
enum ThemeCategory: string
{
    case Efficiency = 'EFFICIENCY';
    case Productivity = 'PRODUCTIVITY';
    case RevenueGm = 'REVENUE_GM';
    case QualityCustomerExperience = 'QUALITY_CUSTOMER_EXPERIENCE';
    case RiskComplianceProcessControl = 'RISK_COMPLIANCE_PROCESS_CONTROL';

    public function label(): string
    {
        return match ($this) {
            self::Efficiency => 'Efficiency',
            self::Productivity => 'Productivity',
            self::RevenueGm => 'Revenue & GM',
            self::QualityCustomerExperience => 'Quality & Customer Experience',
            self::RiskComplianceProcessControl => 'Risk, Compliance & Process Control',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
