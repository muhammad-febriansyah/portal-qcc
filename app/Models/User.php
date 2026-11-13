<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Notifications\VerifyEmailNotification;
use App\Services\ProgramDashboardOverview;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $sim_id
 * @property string|null $avatar_path
 * @property string|null $position
 * @property string|null $work_location
 * @property string|null $availability_status
 * @property list<string>|null $qcc_experience_batches
 * @property bool $needs_profile_setup
 * @property Carbon|null $profile_completed_at
 * @property int|null $organization_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $competencies_count
 * @property int $verified_count
 * @property-read Pivot $pivot
 */
#[Fillable(['name', 'email', 'password', 'sim_id', 'organization_id', 'availability_status', 'position', 'work_location', 'phone_number', 'qcc_experience_batches', 'needs_profile_setup'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'qcc_experience_batches' => 'array',
            'needs_profile_setup' => 'boolean',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * A SIM ID as it is stored: trimmed, upper case, and only the number when it
     * is a "SIM" number ("SIM2551" and "2551" both become "2551").
     */
    public static function normalizeSimId(mixed $simId): mixed
    {
        if (! is_string($simId)) {
            return $simId;
        }

        $simId = strtoupper(trim($simId));

        return preg_match('/^SIM-?(\d+)$/', $simId, $match) === 1 ? $match[1] : $simId;
    }

    /**
     * The first password of an account: "SIM" followed by the SIM ID number,
     * or the SIM ID itself when it is not a number.
     */
    public static function initialPasswordFor(string $simId): string
    {
        return preg_match('/^\d+$/', $simId) === 1 ? 'SIM'.$simId : $simId;
    }

    /**
     * Remember the moment a profile first becomes complete, however it was filled in.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->profile_completed_at === null && $user->hasCompleteProfile()) {
                $user->profile_completed_at = now();
            }
        });
    }

    /**
     * Public URL of the profile photo, or null when none was uploaded.
     *
     * @return Attribute<non-falsy-string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path ? '/storage/'.$this->avatar_path : null);
    }

    /**
     * @return HasMany<MemberCompetency, $this>
     */
    /**
     * Skills this person holds from the current skill dictionary. Entries on retired skills
     * stay in the table but no longer count anywhere.
     */
    public function memberCompetencies(): HasMany
    {
        return $this->hasMany(MemberCompetency::class)
            ->whereHas('competency', fn ($competency) => $competency->where('is_active', true));
    }

    /**
     * Proposals this person wrote, whatever their status.
     *
     * @return HasMany<Project, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Project::class, 'proposer_id');
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    /**
     * The LOBs/Divs this person mentors, set in Master Organisasi. Holding the Mentor role
     * alone does not make someone the mentor of an organization.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function mentoredOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_mentors')->withTimestamps();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasCompleteProfile(): bool
    {
        return filled($this->organization_id)
            && filled($this->position)
            && filled($this->work_location)
            && filled($this->phone_number);
    }

    public function mustCompleteProfile(): bool
    {
        return $this->needs_profile_setup && ! $this->hasCompleteProfile();
    }

    /**
     * Members (not reviewers or admins) must hold at least one competency before they can be picked for a team.
     */
    public function mustFillCompetencies(): bool
    {
        return $this->hasAnyRole(['SIMer / Member', 'Team Leader'])
            && ! $this->can('projects.viewAny')
            && ! $this->memberCompetencies()->exists();
    }

    /**
     * SIMers and Team Leaders get the simple personal dashboard; reviewers, mentors and admins
     * keep the monitoring dashboard, even when they also hold a member role.
     */
    public function usesMemberDashboard(): bool
    {
        return $this->hasAnyRole(['SIMer / Member', 'Team Leader'])
            && ! $this->hasAnyRole(['Owner QCC', 'Mentor', 'Super Admin'])
            && ! $this->can('projects.viewAny')
            && ! $this->can('dashboard.admin');
    }

    /**
     * Program dashboard phases this person may open: Owner QCC, Mentors and admins follow the
     * whole program, Team Leaders only the project execution phase, other SIMers none.
     *
     * @return list<string>
     */
    public function visibleProgramPhases(): array
    {
        if ($this->hasAnyRole(['Owner QCC', 'Mentor']) || $this->can('dashboard.admin')) {
            return ProgramDashboardOverview::PHASES;
        }

        return $this->hasRole('Team Leader') ? ['execution'] : [];
    }

    /**
     * Team Leaders see the program figures of every project but list only their own projects.
     */
    public function seesAllProgramProjects(): bool
    {
        return $this->hasAnyRole(['Owner QCC', 'Mentor']) || $this->can('dashboard.admin');
    }

    /**
     * A Mentor without an overseeing role. Mentor-only limits (reviewing only the projects
     * they mentor, no scoring decisions) apply to them, even when given extra permissions, but
     * not to someone who is a Mentor alongside Owner QCC or Super Admin.
     */
    public function actsOnlyAsMentor(): bool
    {
        return $this->hasRole('Mentor') && ! $this->hasAnyRole(['Owner QCC', 'Super Admin']);
    }

    /**
     * Whether this person is an active, assigned mentor of the project.
     */
    public function mentorsProject(Project $project): bool
    {
        return $this->hasRole('Mentor')
            && $project->mentorAssignments()->where('mentor_id', $this->id)->where('status', 'ACTIVE')->exists();
    }

    public function isInTalentPool(): bool
    {
        return $this->memberCompetencies()->exists();
    }

    /**
     * Sent straight away, so sign-up does not depend on a queue worker running. If the mail
     * server refuses the message now, it goes to the queue and is retried there.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            Notification::sendNow($this, new VerifyEmailNotification);
        } catch (Throwable $exception) {
            Log::warning('Verification email failed to send now; queued for retry.', [
                'exception' => $exception::class,
            ]);

            $this->notify(new VerifyEmailNotification);
        }
    }

    public function auditEventPrefix(): string
    {
        return 'user';
    }

    /**
     * @return list<string>
     */
    protected function auditIgnoredAttributes(): array
    {
        return ['email_verified_at'];
    }

    public function auditLabel(): string
    {
        return $this->name.' ('.$this->email.')';
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function auditUpdatedEvent(array $changes): ?string
    {
        if (array_keys($changes) === ['is_active']) {
            return $changes['is_active'] ? 'user.activated' : 'user.deactivated';
        }

        return null;
    }
}
