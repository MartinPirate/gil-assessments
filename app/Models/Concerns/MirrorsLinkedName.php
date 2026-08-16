<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Keeps a name that is stored twice from drifting apart.
 *
 * A driver and their login are one person recorded in two tables — the driver
 * row carries the operational identity (national ID, phone), the user row
 * carries the credentials — and both hold the name. Neither copy can be
 * dropped: a user who is not a driver still needs a name, and the driver's
 * name is what the gate screens read. So instead of one being authoritative,
 * a rename on either side is mirrored to the other.
 *
 * Documents are pointedly not included. trips.driver_name is a snapshot of who
 * actually drove, and is supposed to keep saying what was true at the time.
 */
trait MirrorsLinkedName
{
    public static function bootMirrorsLinkedName(): void
    {
        static::saved(function (Model $model): void {
            if (! $model->wasChanged('name')) {
                return;
            }

            $counterpart = $model->linkedNameRecord();

            if (! $counterpart || $counterpart->name === $model->name) {
                return;
            }

            // A query update rather than a save: it fires no model events, so
            // the two sides cannot bounce the change back and forth, and the
            // audit trail records the rename once — against the record the
            // person actually edited.
            $counterpart->newQuery()
                ->whereKey($counterpart->getKey())
                ->update(['name' => $model->name]);
        });
    }

    /**
     * The record holding the other copy of this name, if there is one.
     *
     * Read fresh rather than through a cached relation: by the time a save
     * fires, an already-loaded relation may be describing the old state.
     */
    abstract public function linkedNameRecord(): ?Model;
}
