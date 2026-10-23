<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EvidenceLink extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = ['evidence_id', 'target_type', 'target_id', 'created_by'];

    public function auditEventPrefix(): string
    {
        return 'evidence_link';
    }

    public function auditLabel(): string
    {
        return 'Evidence #'.$this->evidence_id;
    }

    /** @return BelongsTo<ProjectEvidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ProjectEvidence::class, 'evidence_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
