<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $head_id
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'head_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The Div or LOB head who mentors and reviews the organization's proposals.
     *
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
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
