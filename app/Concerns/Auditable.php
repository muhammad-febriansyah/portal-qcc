<?php

namespace App\Concerns;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Records create, update and delete of a model in the audit log.
 *
 * Add `use Auditable;` and implement `auditEventPrefix()` (for example `batch`)
 * and `auditLabel()` (a short readable name of the record). Hidden attributes
 * and timestamps are never logged, so passwords and tokens stay out.
 *
 * @mixin Model
 */
trait Auditable
{
    abstract public function auditEventPrefix(): string;

    abstract public function auditLabel(): string;

    /**
     * Pick a more specific event name for an update, or null for the default.
     *
     * @param  array<string, mixed>  $changes
     */
    public function auditUpdatedEvent(array $changes): ?string
    {
        return null;
    }

    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            app(AuditService::class)->record(
                $model->auditEventPrefix().'.created',
                $model,
                after: $model->auditAttributes($model->attributesToArray()),
            );
        });

        static::updated(function (self $model): void {
            $changes = $model->auditAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            app(AuditService::class)->record(
                $model->auditUpdatedEvent($changes) ?? $model->auditEventPrefix().'.updated',
                $model,
                before: Arr::only($model->getOriginal(), array_keys($changes)),
                after: $changes,
            );
        });

        static::deleted(function (self $model): void {
            app(AuditService::class)->record(
                $model->auditEventPrefix().'.deleted',
                $model,
                before: $model->auditAttributes($model->attributesToArray()),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditAttributes(array $attributes): array
    {
        return Arr::except($attributes, [
            ...$this->getHidden(),
            ...$this->getAppends(),
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ]);
    }
}
