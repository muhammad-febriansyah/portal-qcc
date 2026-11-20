<?php

namespace App\Enums;

/**
 * The kind of improvement a proposal aims at, used to group proposals on the program dashboard.
 */
enum ThemeCategory: string
{
    case EmploymentComplianceWorkforceProtection = 'EMPLOYMENT_COMPLIANCE_WORKFORCE_PROTECTION';
    case WorkforceCompetencyProductivity = 'WORKFORCE_COMPETENCY_PRODUCTIVITY';
    case WorkforceBusinessSolutionManagement = 'WORKFORCE_BUSINESS_SOLUTION_MANAGEMENT';
    case DigitalPlatform = 'DIGITAL_PLATFORM';
    case EmployeeWelfareSafetySustainability = 'EMPLOYEE_WELFARE_SAFETY_SUSTAINABILITY';

    public function label(): string
    {
        return match ($this) {
            self::EmploymentComplianceWorkforceProtection => 'Employment Compliance & Workforce Protection',
            self::WorkforceCompetencyProductivity => 'Workforce Competency & Productivity',
            self::WorkforceBusinessSolutionManagement => 'Workforce & Business Solution Management',
            self::DigitalPlatform => 'Digital Platform',
            self::EmployeeWelfareSafetySustainability => 'Employee Welfare, Safety & Sustainability',
        };
    }

    /**
     * What kind of project belongs in the category, shown while choosing one.
     */
    public function description(): string
    {
        return match ($this) {
            self::EmploymentComplianceWorkforceProtection => 'Kepatuhan hubungan kerja, perlindungan hak pekerja, administrasi ketenagakerjaan, dan pengelolaan dokumen.',
            self::WorkforceCompetencyProductivity => 'Peningkatan kompetensi, reskilling, upskilling, produktivitas, dan kesiapan tenaga kerja.',
            self::WorkforceBusinessSolutionManagement => 'Pengelolaan dan pengembangan solusi tenaga kerja serta layanan bisnis (bisnis proses servis) yang terintegrasi dengan kebutuhan dan target klien.',
            self::DigitalPlatform => 'Pengembangan dan optimalisasi platform digital untuk mendukung operasional, kolaborasi, dan layanan bagi perusahaan dan klien.',
            self::EmployeeWelfareSafetySustainability => 'K3, jaminan sosial, kesejahteraan pekerja, pengelolaan risiko, dan keberlanjutan layanan.',
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
