<?php

namespace App\Filament\Concerns;

/**
 * Says which record was saved, rather than "Created".
 *
 * Filament's default notification is a bare verb, which tells you nothing
 * useful when you have just added the fourth VAT code and want to know the one
 * you meant went in. This names the thing: "VAT code TT added successfully".
 *
 * The noun comes from the resource's model label and the name from its record
 * title attribute, so a resource gets this right by declaring what it already
 * should — no per-page strings to keep in step.
 */
trait AnnouncesTheRecord
{
    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->announce('added');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->announce('saved');
    }

    protected function announce(string $verb): string
    {
        $resource = static::getResource();
        $label = ucfirst($resource::getModelLabel());

        $title = filled($this->record?->getKey())
            ? trim((string) $resource::getRecordTitle($this->record))
            : '';

        // A resource with no record-title attribute falls back to the bare
        // noun rather than printing an id nobody recognises.
        $name = ($title === '' || $title === (string) $this->record?->getKey()) ? '' : " {$title}";

        return "{$label}{$name} {$verb} successfully";
    }
}
