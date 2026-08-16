<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $currency
 * @property string|null $kra_pin
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $contact_person_id
 * @property string|null $address_line
 * @property string|null $city
 * @property string|null $county
 * @property string|null $postal_code
 * @property numeric|null $latitude
 * @property numeric|null $longitude
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Collection<int, ContactPerson> $contactPeople
 * @property-read int|null $contact_people_count
 * @property-read ContactPerson|null $contactPerson
 * @property-read Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 *
 * @method static Builder<static>|Customer newModelQuery()
 * @method static Builder<static>|Customer newQuery()
 * @method static Builder<static>|Customer query()
 * @method static Builder<static>|Customer whereAddressLine($value)
 * @method static Builder<static>|Customer whereCity($value)
 * @method static Builder<static>|Customer whereCode($value)
 * @method static Builder<static>|Customer whereContactPersonId($value)
 * @method static Builder<static>|Customer whereCounty($value)
 * @method static Builder<static>|Customer whereCreatedAt($value)
 * @method static Builder<static>|Customer whereCurrency($value)
 * @method static Builder<static>|Customer whereId($value)
 * @method static Builder<static>|Customer whereIsActive($value)
 * @method static Builder<static>|Customer whereKraPin($value)
 * @method static Builder<static>|Customer whereLatitude($value)
 * @method static Builder<static>|Customer whereLongitude($value)
 * @method static Builder<static>|Customer whereName($value)
 * @method static Builder<static>|Customer wherePostalCode($value)
 * @method static Builder<static>|Customer whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Customer extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'contact_person_id',
        'currency',
        'kra_pin',
        'is_active',
        'address_line',
        'city',
        'county',
        'postal_code',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Whether this customer can actually be pinned on a map.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Everyone known at this business partner.
     */
    public function contactPeople(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    /**
     * The one a document raised against this customer defaults to.
     */
    public function contactPerson(): BelongsTo
    {
        return $this->belongsTo(ContactPerson::class);
    }
}
