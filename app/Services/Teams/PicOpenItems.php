<?php

namespace App\Services\Teams;

use App\Enums\ActionStatus;
use App\Enums\RiskStatus;
use App\Models\ActionItem;
use App\Models\Project;
use App\Models\ProjectRisk;
use Illuminate\Support\Collection;

/**
 * Open risks and action items per PIC. Someone who leaves the team can no longer
 * update these, so their open items have to be handed to another member first.
 */
class PicOpenItems
{
    /**
     * @return Collection<int, array{risks: int, actions: int}> keyed by user id
     */
    public function countsByPic(Project $project): Collection
    {
        $risks = ProjectRisk::query()
            ->where('project_id', $project->id)
            ->whereIn('status', $this->openRiskStatuses())
            ->whereNotNull('pic_id')
            ->groupBy('pic_id')
            ->selectRaw('pic_id, COUNT(*) as total')
            ->pluck('total', 'pic_id');

        $actions = ActionItem::query()
            ->where('project_id', $project->id)
            ->whereIn('status', $this->openActionStatuses())
            ->whereNotNull('pic_id')
            ->groupBy('pic_id')
            ->selectRaw('pic_id, COUNT(*) as total')
            ->pluck('total', 'pic_id');

        return $risks->keys()->merge($actions->keys())->unique()->mapWithKeys(fn (int|string $userId): array => [
            (int) $userId => [
                'risks' => (int) ($risks[$userId] ?? 0),
                'actions' => (int) ($actions[$userId] ?? 0),
            ],
        ]);
    }

    /**
     * Hand every open risk and action item of one PIC to another person.
     *
     * @return array{risk_ids: list<int>, action_ids: list<int>}
     */
    public function reassign(Project $project, int $fromUserId, int $toUserId): array
    {
        $riskIds = ProjectRisk::query()
            ->where('project_id', $project->id)
            ->where('pic_id', $fromUserId)
            ->whereIn('status', $this->openRiskStatuses())
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        $actionIds = ActionItem::query()
            ->where('project_id', $project->id)
            ->where('pic_id', $fromUserId)
            ->whereIn('status', $this->openActionStatuses())
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        ProjectRisk::query()->whereIn('id', $riskIds)->update(['pic_id' => $toUserId, 'updated_at' => now()]);
        ActionItem::query()->whereIn('id', $actionIds)->update(['pic_id' => $toUserId, 'updated_at' => now()]);

        return ['risk_ids' => $riskIds, 'action_ids' => $actionIds];
    }

    /**
     * @return list<string>
     */
    private function openRiskStatuses(): array
    {
        return array_values(array_map(
            fn (RiskStatus $status): string => $status->value,
            array_filter(RiskStatus::cases(), fn (RiskStatus $status): bool => ! $status->isFinished()),
        ));
    }

    /**
     * @return list<string>
     */
    private function openActionStatuses(): array
    {
        return array_values(array_map(
            fn (ActionStatus $status): string => $status->value,
            array_filter(ActionStatus::cases(), fn (ActionStatus $status): bool => ! $status->isFinished()),
        ));
    }
}
