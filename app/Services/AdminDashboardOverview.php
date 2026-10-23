<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\CompetencyLevel;
use App\Models\AuditLog;
use App\Models\CompetencyGroup;
use App\Models\Organization;
use App\Models\QccBatch;
use App\Models\RubricSet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numbers for the Super Admin dashboard. Everything is aggregated in the
 * database and kept for a short time, so opening the dashboard stays cheap.
 */
class AdminDashboardOverview
{
    public const CACHE_KEY = 'dashboard.admin.overview';

    public const DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(2), fn (): array => $this->build());
    }

    /**
     * Active people who take part in QCC: everyone except accounts that are only Super Admin.
     *
     * @return Builder<User>
     */
    private function participants(): Builder
    {
        return User::query()
            ->where('users.is_active', true)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('roles')
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles->where('name', '!=', 'Super Admin'));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $today = CarbonImmutable::today();
        $weekAgo = $today->subDays(6)->startOfDay();
        $previousWeekStart = $today->subDays(13)->startOfDay();

        $participantCount = $this->participants()->count();
        $filledCount = $this->participants()->has('memberCompetencies')->count();

        $thisWeek = AuditLog::query()->where('occurred_at', '>=', $weekAgo)->count();
        $lastWeek = AuditLog::query()
            ->where('occurred_at', '>=', $previousWeekStart)
            ->where('occurred_at', '<', $weekAgo)
            ->count();

        $recent = AuditLog::query()->with('actor:id,name')->latest('occurred_at')->latest('id')->limit(8)->get();
        $describer = AuditDescriber::forLogs($recent);

        return [
            'cards' => [
                'members_active' => User::query()->where('is_active', true)->count(),
                'members_inactive' => User::query()->where('is_active', false)->count(),
                'participants' => $participantCount,
                'participants_filled' => $filledCount,
                'batches_active' => QccBatch::query()->where('status', BatchStatus::Active->value)->count(),
                'batches_total' => QccBatch::query()->count(),
                'activity_week' => $thisWeek,
                'activity_previous_week' => $lastWeek,
            ],
            'activity' => $this->activitySeries($today),
            'competency_levels' => $this->competencyLevels(),
            'members_by_organization' => $this->membersByOrganization(),
            'attention' => $this->attention($participantCount, $filledCount),
            'recent' => $recent->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'description' => $describer->describe($log),
                'module' => $log->moduleLabel(),
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ])->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * How many competency entries sit at each level, per competency group.
     * The verified level counts first, otherwise the self assessed level.
     *
     * @return array{groups: list<string>, levels: array<int, array{label: non-falsy-string, data: list<int>}>}
     */
    private function competencyLevels(): array
    {
        $groups = CompetencyGroup::query()->orderBy('code')->get(['id', 'code', 'name']);
        $effectiveLevel = 'COALESCE(member_competencies.verified_level, member_competencies.self_level)';

        /** @var Collection<int, object{group_id: int, level: int, total: int}> $rows */
        $rows = DB::table('member_competencies')
            ->join('users', 'users.id', '=', 'member_competencies.user_id')
            ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
            ->where('users.is_active', true)
            ->selectRaw("competency_master.group_id as group_id, {$effectiveLevel} as level, COUNT(*) as total")
            ->groupBy('competency_master.group_id', DB::raw($effectiveLevel))
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->group_id][(int) $row->level] = (int) $row->total;
        }

        $levels = [];

        foreach (CompetencyLevel::cases() as $level) {
            $levels[$level->value] = [
                'label' => $level->value.' · '.$level->label(),
                'data' => array_values($groups->map(fn (CompetencyGroup $group): int => $counts[$group->id][$level->value] ?? 0)->all()),
            ];
        }

        return [
            'groups' => array_values($groups->map(fn (CompetencyGroup $group): string => $group->name)->all()),
            'levels' => $levels,
        ];
    }

    /**
     * Participants per LOB or division, including the ones with no organization yet.
     *
     * @return list<array{label: string, total: int}>
     */
    private function membersByOrganization(): array
    {
        $counts = $this->participants()
            ->whereNotNull('users.organization_id')
            ->select('users.organization_id', DB::raw('COUNT(*) as total'))
            ->groupBy('users.organization_id')
            ->pluck('total', 'organization_id');

        $rows = Organization::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization): array => [
                'label' => $organization->name,
                'total' => (int) ($counts[$organization->id] ?? 0),
            ])
            ->all();

        $unassigned = $this->participants()->whereNull('users.organization_id')->count();

        if ($unassigned > 0) {
            $rows[] = ['label' => 'Tanpa organisasi', 'total' => $unassigned];
        }

        usort($rows, fn (array $a, array $b): int => [$b['total'], $a['label']] <=> [$a['total'], $b['label']]);

        return $rows;
    }

    /**
     * Things the admin should fix. Only items with something to do are returned.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function attention(int $participantCount, int $filledCount): array
    {
        $items = [
            ['key' => 'no_organization', 'label' => 'Peserta belum punya LOB / Divisi', 'count' => $this->participants()->whereNull('users.organization_id')->count()],
            ['key' => 'no_sim_id', 'label' => 'Peserta belum punya SIM ID', 'count' => $this->participants()->whereNull('users.sim_id')->count()],
            ['key' => 'no_competency', 'label' => 'Peserta belum mengisi kompetensi', 'count' => $participantCount - $filledCount],
            ['key' => 'unpublished_rubric', 'label' => 'Rubrik penilaian belum dipublikasikan', 'count' => RubricSet::query()->whereDoesntHave('versions', fn (Builder $versions): Builder => $versions->where('publication_status', 'published'))->count()],
        ];

        if (Schema::hasTable('system_settings')) {
            $settings = DB::table('system_settings')->whereIn('key', ['ai_api_key', 'ai_model'])->pluck('value', 'key');
            $items[] = [
                'key' => 'ai_not_configured',
                'label' => 'Integrasi AI belum diatur (kunci atau model)',
                'count' => filled($settings['ai_api_key'] ?? null) && filled($settings['ai_model'] ?? null) ? 0 : 1,
            ];
        }

        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * Logins, data changes and failed logins for each of the last DAYS days.
     *
     * @return array{days: list<string>, login: list<int>, changes: list<int>, failed: list<int>}
     */
    private function activitySeries(CarbonImmutable $today): array
    {
        $from = $today->subDays(self::DAYS - 1)->startOfDay();

        /** @var Collection<int, object{day: string, event: string, total: int}> $rows */
        $rows = AuditLog::query()
            ->where('occurred_at', '>=', $from)
            ->select(DB::raw('DATE(occurred_at) as day'), 'event', DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(occurred_at)'), 'event')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $bucket = match (true) {
                $row->event === 'auth.login' => 'login',
                $row->event === 'auth.login_failed' => 'failed',
                str_starts_with($row->event, 'auth.') => null,
                default => 'changes',
            };

            if ($bucket !== null) {
                $day = (string) $row->day;
                $byDay[$day][$bucket] = ($byDay[$day][$bucket] ?? 0) + (int) $row->total;
            }
        }

        $series = ['days' => [], 'login' => [], 'changes' => [], 'failed' => []];

        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $day = $from->addDays($offset)->toDateString();
            $series['days'][] = $day;
            $series['login'][] = $byDay[$day]['login'] ?? 0;
            $series['changes'][] = $byDay[$day]['changes'] ?? 0;
            $series['failed'][] = $byDay[$day]['failed'] ?? 0;
        }

        return $series;
    }
}
