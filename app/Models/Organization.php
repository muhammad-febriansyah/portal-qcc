<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The mentors who review and mentor the organization's proposals. Most have one; a few
     * (LOB CCCM, KISS) share two, and either of them may review.
     *
     * @return BelongsToMany<User, $this>
     */
    public function mentors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_mentors')->withTimestamps();
    }

    /**
     * People who work in this LOB/Div.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function auditEventPrefix(): string
    {
        return 'organization';
    }

    public function auditLabel(): string
    {
        return $this->code.' - '.$this->name;
    }
}
