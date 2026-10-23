<?php

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\BiweeklyReport;
use App\Models\Competency;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectMentor;
use App\Models\ProjectRisk;
use App\Models\QccBatch;
use App\Models\ReviewAssignment;
use App\Models\User;
use Database\Seeders\DemoQccSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

it('seeds realistic local demo data idempotently without changing existing accounts', function () {
    Storage::fake(config('qcc_evidence.disk'));

    $existingUser = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'keep@example.test',
    ]);
    $auditLogCount = AuditLog::query()->count();

    $seeder = app(DemoQccSeeder::class);
    $seeder->run();
    $seeder->run();

    expect(User::query()->where('email', 'like', 'demo.%@example.test')->count())->toBe(75)
        ->and(QccBatch::query()->where('code', 'like', 'DEMO-QCC-%')->count())->toBe(3)
        ->and(Project::query()->where('title', 'like', '[DEMO] %')->count())->toBe(15)
        ->and(Project::query()->where('proposal_status', ProposalStatus::Approved)->count())->toBe(3)
        ->and(Project::query()->where('proposal_status', ProposalStatus::Approved)->withCount('steps')->get()->sum('steps_count'))->toBe(24)
        ->and(Project::query()->where('title', 'like', '[DEMO] %')->withCount('members')->get()->sum('members_count'))->toBe(30)
        ->and(BiweeklyReport::query()->count())->toBe(15)
        ->and(MentoringReport::query()->count())->toBe(15)
        ->and(ProjectMentor::query()->count())->toBe(3)
        ->and(ReviewAssignment::query()->where('scope', 'proposal')->count())->toBe(9)
        ->and(ProjectRisk::query()->count())->toBe(15)
        ->and(ActionItem::query()->count())->toBe(15)
        ->and(ProjectEvidence::query()->count())->toBe(15)
        ->and(AuditLog::query()->count())->toBe($auditLogCount)
        ->and(Competency::query()->count())->toBe(0)
        ->and($existingUser->fresh()->name)->toBe('Existing User');

    foreach (['Super Admin', 'Owner QCC', 'Mentor', 'Team Leader', 'SIMer / Member'] as $roleName) {
        expect(Role::findByName($roleName, 'web')->users()->where('email', 'like', 'demo.%@example.test')->count())->toBe(15);
    }

    expect(Hash::check('password', User::query()->where('email', 'demo.team-leader.01@example.test')->firstOrFail()->password))->toBeTrue();
    Storage::disk(config('qcc_evidence.disk'))->assertExists('demo/projects/'.Project::query()->where('title', 'like', '[DEMO] %')->where('proposal_status', ProposalStatus::Approved)->orderBy('id')->value('id').'/evidence-01.png');
});

it('refuses to seed demo data outside local or testing environments', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoQccSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'Demo QCC data may only be seeded into a local or test database.');

    expect(User::query()->count())->toBe(0);
});
