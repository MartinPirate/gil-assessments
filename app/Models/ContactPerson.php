<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named person at a business partner, with what you would need to reach them.
 *
 * A customer may know several; the one on customers.contact_person_id is the
 * one documents raised against that customer default to.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AuditLog> $auditLogs
 * @property-read int|null $audit_logs_count
 * @property-read Customer $customer
 *
 * @method static Builder<static>|ContactPerson newModelQuery()
 * @method static Builder<static>|ContactPerson newQuery()
 * @method static Builder<static>|ContactPerson query()
 * @method static Builder<static>|ContactPerson whereCreatedAt($value)
 * @method static Builder<static>|ContactPerson whereCustomerId($value)
 * @method static Builder<static>|ContactPerson whereEmail($value)
 * @method static Builder<static>|ContactPerson whereId($value)
 * @method static Builder<static>|ContactPerson whereIsActive($value)
 * @method static Builder<static>|ContactPerson whereName($value)
 * @method static Builder<static>|ContactPerson wherePhone($value)
 * @method static Builder<static>|ContactPerson whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class ContactPerson extends Model
{
    use Auditable;
    use HasFactory;

    protected $table = 'contact_people';

    protected $fillable = [
        'customer_id', 'name', 'email', 'phone', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // A customer's first contact becomes its default, so a business
        // partner is never left pointing at nobody while its contact list
        // is plainly not empty. Conditional in the UPDATE rather than an
        // if() around it: two contacts saved at once must not race into
        // both claiming the role.
        static::created(function (self $contact): void {
            $contact->customer()
                ->whereNull('contact_person_id')
                ->update(['contact_person_id' => $contact->getKey()]);
        });

        // Removing the default must not leave the customer pointing at a row
        // that no longer exists — hand the role on, or clear it.
        //
        // Before the delete, not after: the foreign key is enforced by the
        // database, so a "deleted" hook never gets to run — SQL Server rejects
        // the DELETE while the customer still references this row.
        static::deleting(function (self $contact): void {
            Customer::query()
                ->where('contact_person_id', $contact->getKey())
                ->update([
                    'contact_person_id' => static::query()
                        ->where('customer_id', $contact->customer_id)
                        ->whereKeyNot($contact->getKey())
                        ->orderBy('id')
                        ->value('id'),
                ]);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
