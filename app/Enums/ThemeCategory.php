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
     * What kind of project belongs in the category, shown while choosing one.
     */
    public function description(): string
    {
        return match ($this) {
            self::NewRevenueStream => 'Proyek yang menciptakan sumber pendapatan baru melalui layanan tambahan, paket layanan terintegrasi, pemanfaatan kapabilitas atau teknologi, model bisnis baru, maupun perluasan pasar.',
            self::QualityCustomerExperience => 'Proyek yang meningkatkan kualitas produk/layanan, akurasi proses, serta kepuasan pelanggan dengan mengurangi error, komplain, dan meningkatkan service level.',
            self::CostEfficiency => 'Proyek yang mengurangi biaya, pemborosan (waste), dan mengoptimalkan penggunaan resource melalui proses yang lebih efisien.',
            self::Productivity => 'Proyek yang meningkatkan output, kapasitas, efektivitas, dan produktivitas sumber daya (manpower, material, equipment, time).',
            self::DeliveryServiceExcellence => 'Proyek yang meningkatkan ketepatan waktu, kecepatan, keandalan, dan pemenuhan layanan (SLA).',
            self::SafetyRiskCompliance => 'Proyek yang memperkuat keselamatan kerja, pengendalian risiko, kepatuhan terhadap regulasi, dan pengendalian proses untuk memastikan operasional yang aman, andal, dan berkelanjutan.',
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label(), 'description' => $category->description()],
            self::cases(),
        );
    }
}
