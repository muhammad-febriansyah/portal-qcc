<?php

namespace App\Enums;

/**
 * Shared by bi-weekly reports and mentoring reports. Mentoring uses SUBMITTED and
 * ACKNOWLEDGED; bi-weekly uses SUBMITTED, APPROVED and REVISION_REQUIRED.
 */
enum ReportStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Acknowledged = 'ACKNOWLEDGED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::RevisionRequired => 'Perlu revisi',
            self::Acknowledged => 'Ditanggapi tim',
        };
    }
}
