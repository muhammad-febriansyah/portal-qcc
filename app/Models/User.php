<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Notifications\VerifyEmailNotification;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

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
 * @property int|null $organization_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $competencies_count
 * @property int $verified_count
 * @property-read Pivot $pivot
 */
#[Fillable(['name', 'email', 'password', 'sim_id', 'organization_id', 'availability_status', 'position', 'work_location', 'work_contact', 'personal_contact', 'qcc_experience_batches', 'needs_profile_setup'])]
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
        ];
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
    public function memberCompetencies(): HasMany
    {
        return $this->hasMany(MemberCompetency::class);
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
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
            && filled($this->availability_status);
    }

    public function mustCompleteProfile(): bool
    {
        return $this->needs_profile_setup && ! $this->hasCompleteProfile();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function auditEventPrefix(): string
    {
        return 'user';
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
