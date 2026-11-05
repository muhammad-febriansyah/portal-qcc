<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case RevisionRequired = 'REVISION_REQUIRED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Sedang direview',
            self::RevisionRequired => 'Perlu revisi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Owner QCC builds the team while selecting a submitted proposal, not while it is still a draft.
     *
     * @return list<self>
     */
    public static function teamFormationStatuses(): array
    {
        return [self::Submitted, self::UnderReview, self::RevisionRequired];
    }

    public function allowsTeamFormation(): bool
    {
        return in_array($this, self::teamFormationStatuses(), true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
