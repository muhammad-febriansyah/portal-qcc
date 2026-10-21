<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Keys that must never be written to the log, at any depth.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'secret',
        'api_key',
    ];

    private ?string $traceId = null;

    /**
     * One identifier shared by every entry written during the same request or command.
     */
    public function traceId(): string
    {
        return $this->traceId ??= (string) Str::uuid();
    }

    /**
     * Append an entry. Sensitive values are removed before anything is stored.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $event,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?string $subjectLabel = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel ?? $this->labelFor($subject),
            'trace_id' => $this->traceId(),
            'before_json' => $before === null ? null : $this->scrub($before),
            'after_json' => $after === null ? null : $this->scrub($after),
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function scrub(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }

    private function labelFor(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        return method_exists($subject, 'auditLabel')
            ? $subject->auditLabel()
            : class_basename($subject).' #'.$subject->getKey();
    }
}
