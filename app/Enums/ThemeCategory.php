<?php

namespace App\Enums;

/**
 * The kind of improvement a proposal aims at, used to group proposals on the program dashboard.
 */
enum ThemeCategory: string
{
    case NewRevenueStream = 'NEW_REVENUE_STREAM';
    case QualityCustomerExperience = 'QUALITY_CUSTOMER_EXPERIENCE';
    case CostEfficiency = 'COST_EFFICIENCY';
    case Productivity = 'PRODUCTIVITY';
    case DeliveryServiceExcellence = 'DELIVERY_SERVICE_EXCELLENCE';
    case SafetyRiskCompliance = 'SAFETY_RISK_COMPLIANCE';

    public function label(): string
    {
        return match ($this) {
            self::NewRevenueStream => 'New Revenue Stream',
            self::QualityCustomerExperience => 'Quality & Customer Experience',
            self::CostEfficiency => 'Cost & Efficiency',
            self::Productivity => 'Productivity',
            self::DeliveryServiceExcellence => 'Delivery & Service Excellence',
            self::SafetyRiskCompliance => 'Safety, Risk, & Compliance',
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
