<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon|string|null $created_at
 * @property array<string, mixed>|null $metadata_json
 */
class ProjectEvidence extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = [
        'project_id', 'uploaded_by', 'title', 'category', 'description', 'source_type',
        'url', 'summary',
        'storage_disk', 'storage_key', 'original_name', 'file_extension', 'mime_type',
        'size_bytes', 'checksum_sha256', 'version_group', 'version_number', 'supersedes_id',
        'scan_status', 'scan_error', 'processing_status', 'extraction_status',
        'processing_error', 'thumbnail_path', 'extracted_text_path', 'metadata_json',
    ];

    /** @var list<string> */
    protected $hidden = [
        'storage_disk', 'storage_key', 'checksum_sha256', 'scan_error', 'processing_error',
        'thumbnail_path', 'extracted_text_path', 'url', 'summary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version_number' => 'integer',
            'metadata_json' => 'array',
        ];
    }

    public function auditEventPrefix(): string
    {
        return 'evidence';
    }

    public function auditLabel(): string
    {
        return Str::limit($this->title, 180).' · v'.$this->version_number;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<ProjectEvidence, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<EvidenceLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EvidenceLink::class, 'evidence_id');
    }
}
