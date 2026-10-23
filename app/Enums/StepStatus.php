<?php

namespace App\Enums;

enum StepStatus: string
{
    case NotStarted = 'NOT_STARTED';
    case InProgress = 'IN_PROGRESS';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case RevisionRequired = 'REVISION_REQUIRED';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum dimulai',
            self::InProgress => 'Sedang dikerjakan',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Sedang direview',
            self::Approved => 'Disetujui',
            self::RevisionRequired => 'Perlu revisi',
        };
    }
}
