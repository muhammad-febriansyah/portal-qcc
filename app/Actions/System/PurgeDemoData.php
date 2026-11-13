<?php

namespace App\Actions\System;

use App\Models\Project;
use App\Models\QccBatch;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Removes the demo accounts and demo QCC data seeded for trials, leaving real accounts and
 * their data alone. Demo accounts use an "@example.test" email and demo batches a "DEMO-"
 * code; every project proposed by a demo account or held in a demo batch goes with them.
 */
class PurgeDemoData
{
    public const DEMO_EMAIL_DOMAIN = '@example.test';

    public const DEMO_BATCH_PREFIX = 'DEMO-';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * What a purge run by this person would remove. Their own account is never removed, so it
     * is not counted; when it is itself a demo account that is reported separately.
     *
     * @return array{users: int, projects: int, batches: int, viewer_is_demo: bool}
     */
    public function summary(User $viewer): array
    {
        return [
            'users' => $this->demoUsers()->whereKeyNot($viewer->id)->count(),
            'projects' => $this->demoProjects()->count(),
            'batches' => $this->demoBatches()->count(),
            'viewer_is_demo' => self::isDemoAccount($viewer),
        ];
    }

    public static function isDemoAccount(User $user): bool
    {
        return str_ends_with(strtolower($user->email), self::DEMO_EMAIL_DOMAIN);
    }

    /**
     * The person running the purge keeps their account, even when it is a demo account, so
     * they are not signed out halfway; they can remove it later from user management.
     *
     * @return array{users: int, deactivated: int, projects: int, batches: int}
     */
    public function handle(User $actor): array
    {
        $files = [];

        $result = DB::transaction(function () use ($actor, &$files): array {
            $projectIds = $this->demoProjects()->pluck('id');
            $files = $this->storedFiles($projectIds->all());

            ScoringRun::query()->whereIn('project_id', $projectIds)->delete();
            Project::withTrashed()->whereKey($projectIds)->forceDelete();

            $batchIds = $this->demoBatches()->pluck('id');
            DB::table('qcc_batch_schedules')->whereIn('batch_id', $batchIds)->delete();
            $batches = QccBatch::query()->whereKey($batchIds)->delete();

            $deleted = 0;
            $deactivated = 0;

            foreach ($this->demoUsers()->whereKeyNot($actor->id)->get() as $user) {
                $this->deleteUser($user) ? $deleted++ : $deactivated++;
            }

            $summary = ['users' => $deleted, 'deactivated' => $deactivated, 'projects' => $projectIds->count(), 'batches' => $batches];
            $this->audit->record('system_setting.demo_data_purged', after: $summary, subjectLabel: 'Data dummy');

            return $summary;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->deleteFiles($files);

        return $result;
    }

    /**
     * @return Builder<User>
     */
    private function demoUsers(): Builder
    {
        return User::query()->where('email', 'like', '%'.self::DEMO_EMAIL_DOMAIN);
    }

    /**
     * @return Builder<QccBatch>
     */
    private function demoBatches(): Builder
    {
        return QccBatch::query()->where('code', 'like', self::DEMO_BATCH_PREFIX.'%');
    }

    /**
     * @return Builder<Project>
     */
    private function demoProjects(): Builder
    {
        return Project::withTrashed()->where(fn (Builder $query): Builder => $query
            ->whereIn('proposer_id', $this->demoUsers()->select('id'))
            ->orWhereIn('batch_id', $this->demoBatches()->select('id')));
    }

    /**
     * A demo account that real data still points to (for example a review it wrote on a real
     * project) cannot be deleted without losing that history, so it is deactivated instead.
     */
    private function deleteUser(User $user): bool
    {
        try {
            DB::transaction(function () use ($user): void {
                $user->roles()->detach();
                $user->permissions()->detach();
                $user->notifications()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('users')->where('id', $user->id)->delete();
            });

            return true;
        } catch (QueryException) {
            $user->forceFill(['is_active' => false])->saveQuietly();

            return false;
        }
    }

    /**
     * Proposal attachments and evidence files of the demo projects.
     *
     * @param  list<int>  $projectIds
     * @return list<array{disk: string, path: string}>
     */
    private function storedFiles(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $attachments = DB::table('project_attachments')->whereIn('project_id', $projectIds)->get(['disk', 'path'])
            ->map(fn (object $file): array => ['disk' => (string) $file->disk, 'path' => (string) $file->path]);

        $evidence = DB::table('project_evidence')->whereIn('project_id', $projectIds)
            ->get(['storage_disk', 'storage_key', 'thumbnail_path', 'extracted_text_path'])
            ->flatMap(fn (object $file): array => collect([$file->storage_key, $file->thumbnail_path, $file->extracted_text_path])
                ->filter()
                ->map(fn (string $path): array => ['disk' => (string) ($file->storage_disk ?: config('filesystems.default')), 'path' => $path])
                ->all());

        return $attachments->merge($evidence)->filter(fn (array $file): bool => $file['path'] !== '')->values()->all();
    }

    /**
     * @param  list<array{disk: string, path: string}>  $files
     */
    private function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                Log::warning('A demo file could not be deleted.', ['disk' => $file['disk'], 'exception' => $exception::class]);
            }
        }
    }
}
