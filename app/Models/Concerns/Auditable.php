<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records every create, update and delete against the model, with the actor.
 *
 * Approvals, payments and gate movements are consequential, so "the system did
 * it" is not an acceptable answer — each change has to be attributable.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAuditLog(AuditLog::CREATED, [], $model->auditableAttributes($model->getAttributes())));

        static::updated(function (Model $model) {
            $changed = $model->auditableAttributes($model->getChanges());

            // Ignore no-op saves and timestamp-only touches — an audit trail
            // full of "updated_at changed" hides the entries that matter.
            if ($changed === []) {
                return;
            }

            $old = collect($model->getOriginal())
                ->only(array_keys($changed))
                ->all();

            $model->writeAuditLog(AuditLog::UPDATED, $model->auditableAttributes($old), $changed);
        });

        static::deleted(fn (Model $model) => $model->writeAuditLog(AuditLog::DELETED, $model->auditableAttributes($model->getOriginal()), []));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /**
     * Attributes never worth recording, or actively dangerous to record.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'remember_token', 'password'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );
    }

    /**
     * Strip noise and secrets before anything is written.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditableAttributes(array $attributes): array
    {
        $excluded = $this->auditExcluded();

        return collect($attributes)
            ->reject(fn ($value, string $key) => in_array($key, $excluded, true))
            // Defence in depth: never let a secret-looking key through even if
            // a future column is added and nobody updates the exclude list.
            ->reject(fn ($value, string $key) => (bool) preg_match('/password|token|secret|api_key/i', $key))
            ->map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value)
            ->all();
    }

    /**
     * How this record should be named in the log.
     */
    public function auditLabel(): ?string
    {
        foreach (['document_number', 'reference', 'code', 'vehicle_number', 'trans_id', 'name', 'email'] as $attribute) {
            if (filled($this->{$attribute} ?? null)) {
                return (string) $this->{$attribute};
            }
        }

        return (string) $this->getKey();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function writeAuditLog(string $event, array $old, array $new): void
    {
        $user = Auth::user();
        $request = request();

        AuditLog::create([
            'user_id' => $user?->getKey(),
            // Snapshotted: the log must still read correctly if the user is
            // renamed or deactivated later.
            'user_name' => $user?->name,
            // role() rather than ->role: the role is a Laratrust row now, not
            // a column, so there is no attribute of that name to read.
            'user_role' => $user?->role()?->value,
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'auditable_label' => $this->auditLabel(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 500) ?: null,
            'url' => substr((string) $request?->fullUrl(), 0, 500) ?: null,
        ]);
    }
}
