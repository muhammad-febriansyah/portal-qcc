<?php

namespace App\Enums;

enum ScoringStatus: string
{
    case EvidenceCheck = 'evidence_check';
    case ReadyForScoring = 'ready_for_scoring';
    case AiScoring = 'ai_scoring';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case InsufficientEvidence = 'insufficient_evidence';
    case Failed = 'failed';
    case RevisionRequired = 'revision_required';

    public function label(): string
    {
        return match ($this) {
            self::EvidenceCheck => 'Memeriksa evidence',
            self::ReadyForScoring => 'Siap dinilai',
            self::AiScoring => 'AI sedang menilai',
            self::PendingReview => 'Menunggu review',
            self::Approved => 'Disetujui',
            self::InsufficientEvidence => 'Evidence belum cukup',
            self::Failed => 'Gagal',
            self::RevisionRequired => 'Perlu revisi',
        };
    }

    /**
     * Statuses after which the run can no longer change on its own.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Approved, self::InsufficientEvidence, self::Failed, self::RevisionRequired], true);
    }
}
