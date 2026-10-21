<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\AuditLog;
use App\Models\QccBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $today = CarbonImmutable::today();
        $weekAgo = $today->subDays(6)->startOfDay();
        $previousWeekStart = $today->subDays(13)->startOfDay();

        $participants = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('roles')
                    ->orWhereHas('roles', fn (Builder $roles): Builder => $roles->where('name', '!=', 'Super Admin'));
            });
        $participantCount = (clone $participants)->count();
        $filledCount = (clone $participants)->has('memberCompetencies')->count();

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
