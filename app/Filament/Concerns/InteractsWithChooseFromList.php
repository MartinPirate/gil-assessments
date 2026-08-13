<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * Shared wiring for the SAP-style "Choose From List" picker.
 *
 * Extracted so the A/R Invoice and both gate screens use one implementation —
 * the state-path safety check below in particular must not exist in three
 * slightly different copies.
 */
trait InteractsWithChooseFromList
{
    /**
     * The orange drill-arrow button that opens the shared picker modal.
     */
    protected function chooseFromListAction(string $source): Action
    {
        return Action::make('chooseFromList_'.$source)
            ->label('Choose From List')
            ->icon(Heroicon::ArrowRightCircle)
            ->color('warning')
            ->action(function (Component $component) use ($source) {
                $this->dispatch(
                    'open-choose-from-list',
                    source: $source,
                    statePath: $component->getStatePath(),
                );
            });
    }

    /**
     * Whether a state path received from the browser may be written to.
     *
     * Two things are checked:
     *  - it must live inside this form's own state (the `data.` prefix), so the
     *    picker cannot overwrite arbitrary public properties on the component;
     *  - it must not contain a `*`, because data_set() treats that as a
     *    wildcard and would write the chosen id into every sibling key.
     */
    protected function isWritableStatePath(?string $statePath): bool
    {
        if ($statePath === null) {
            return false;
        }

        return str_starts_with($statePath, 'data.') && ! str_contains($statePath, '*');
    }

    /**
     * A Set-style callback scoped to the siblings of $statePath.
     *
     * This lets the apply* helpers behave identically whether they were invoked
     * by a field's afterStateUpdated hook or by a selection from the modal.
     */
    protected function siblingSetter(string $statePath): callable
    {
        $relative = substr($statePath, strlen('data.'));

        $prefix = str_contains($relative, '.')
            ? substr($relative, 0, strrpos($relative, '.') + 1)
            : '';

        return function (string $key, mixed $value) use ($prefix): void {
            data_set($this, 'data.'.$prefix.$key, $value);
        };
    }

    /**
     * The matching getter for {@see siblingSetter()}.
     */
    protected function siblingGetter(string $statePath): callable
    {
        $relative = substr($statePath, strlen('data.'));

        $prefix = str_contains($relative, '.')
            ? substr($relative, 0, strrpos($relative, '.') + 1)
            : '';

        return fn (string $key): mixed => data_get($this, 'data.'.$prefix.$key);
    }
}
