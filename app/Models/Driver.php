<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\MirrorsLinkedName;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $name
 * @property string $national_id
 * @property string $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $user_id
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Collection<int, GateLog> $gateLogs
 * @property-read int|null $gate_logs_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Trip> $trips
 * @property-read int|null $trips_count
 * @property-read User $user
 *
 * @method static \Database\Factories\DriverFactory factory($count = null, $state = [])
 * @method static Builder<static>|Driver newModelQuery()
 * @method static Builder<static>|Driver newQuery()
 * @method static Builder<static>|Driver query()
 * @method static Builder<static>|Driver whereCreatedAt($value)
 * @method static Builder<static>|Driver whereId($value)
 * @method static Builder<static>|Driver whereIsActive($value)
 * @method static Builder<static>|Driver whereName($value)
 * @method static Builder<static>|Driver whereNationalId($value)
 * @method static Builder<static>|Driver wherePhone($value)
 * @method static Builder<static>|Driver whereUpdatedAt($value)
 * @method static Builder<static>|Driver whereUserId($value)
 *
 * @mixin Eloquent
 */
class Driver extends Model implements HasMedia
{
    public const LICENCE = 'licence';

    use Auditable;
    use HasFactory;
    use InteractsWithMedia;
    use MirrorsLinkedName;

    protected $fillable = ['user_id', 'name', 'national_id', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Every driver has a login — the column is NOT NULL — so this is a
     * required relation, not an optional one.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function linkedNameRecord(): ?Model
    {
        return $this->user()->first();
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class)->latest('scheduled_at');
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class)->latest('time_in');
    }

    /**
     * The licence a driver is carrying.
     *
     * One file, replaced rather than added to: a driver has one current
     * licence, and keeping the old scan alongside it only invites somebody to
     * read the wrong one. Restricted to a photograph or a PDF, which is what a
     * gate officer will actually have.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LICENCE)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
    }

    public function licence(): ?Media
    {
        return $this->getFirstMedia(self::LICENCE);
    }

    public function hasLicence(): bool
    {
        return $this->licence() !== null;
    }
}
