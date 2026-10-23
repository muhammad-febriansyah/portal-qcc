<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class NotificationKinds
{
    private const GROUPS = [
        'proposal' => ['label' => 'Proposal', 'icon' => 'file-text'],
        'step_qcc' => ['label' => 'Step QCC', 'icon' => 'list-checks'],
        'biweekly' => ['label' => 'Bi-weekly', 'icon' => 'calendar-days'],
        'mentoring' => ['label' => 'Mentoring', 'icon' => 'messages-square'],
        'risk_action' => ['label' => 'Risiko & Tindakan', 'icon' => 'triangle-alert'],
        'ai_scoring' => ['label' => 'AI Scoring', 'icon' => 'sparkles'],
        'other' => ['label' => 'Notifikasi', 'icon' => 'bell'],
    ];

    private const PROPOSAL_TYPES = [
        'SUBMITTED', 'RESUBMITTED', 'APPROVE', 'REQUEST_REVISION', 'REJECT', 'TEAM_COMPOSITION_PENDING',
    ];

    private const RISK_ACTION_TYPES = ['RISK_ASSIGNED', 'ACTION_ASSIGNED', 'OVERDUE', 'overdue'];

    /**
     * @return array{kind: string, kind_label: string, kind_icon: string}
     */
    public static function describe(?string $decision): array
    {
        $type = strtoupper(trim((string) $decision));
        $kind = match (true) {
            in_array($type, self::PROPOSAL_TYPES, true) => 'proposal',
            str_starts_with($type, 'STEP_') => 'step_qcc',
            str_starts_with($type, 'BIWEEKLY_') => 'biweekly',
            $type === 'MENTOR_ASSIGNED' || str_starts_with($type, 'MENTORING_') => 'mentoring',
            in_array($type, self::RISK_ACTION_TYPES, true) || str_starts_with($type, 'DUE_SOON') => 'risk_action',
            str_starts_with($type, 'SCORING_') => 'ai_scoring',
            default => 'other',
        };
        $group = self::GROUPS[$kind];

        return [
            'kind' => $kind,
            'kind_label' => $group['label'],
            'kind_icon' => $group['icon'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::GROUPS as $value => $group) {
            if ($value === 'other') {
                continue;
            }

            $groups[] = [
                'value' => $value,
                'label' => $group['label'],
            ];
        }

        return $groups;
    }

    /** @param Builder<DatabaseNotification> $query */
    public static function applyGroupFilter(Builder $query, string $kind): void
    {
        match ($kind) {
            'proposal' => $query->whereIn('data->decision', self::PROPOSAL_TYPES),
            'step_qcc' => $query->where('data->decision', 'like', 'STEP_%'),
            'biweekly' => $query->where('data->decision', 'like', 'BIWEEKLY_%'),
            'mentoring' => $query->where(fn (Builder $query): Builder => $query
                ->where('data->decision', 'MENTOR_ASSIGNED')
                ->orWhere('data->decision', 'like', 'MENTORING_%')),
            'risk_action' => $query->where(fn (Builder $query): Builder => $query
                ->whereIn('data->decision', self::RISK_ACTION_TYPES)
                ->orWhere('data->decision', 'like', 'DUE_SOON%')),
            'ai_scoring' => $query->where('data->decision', 'like', 'SCORING_%'),
            default => null,
        };
    }
}
