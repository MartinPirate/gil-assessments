<?php

namespace App\Livewire;

use App\Support\ChooseFromListRegistry;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The SAP-style "Choose From List" picker.
 *
 * One instance is mounted per page and re-targeted at whichever field asked
 * for it, so every CFL button on the screen shares a single searchable,
 * sortable, paginated modal instead of each field shipping its own.
 */
class ChooseFromList extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    /** The Filament modal this component renders. */
    public const MODAL_ID = 'choose-from-list';

    /** Registry key of the list currently being shown. */
    public string $sourceKey = 'customers_by_code';

    /** Dot path of the form field that opened the picker. */
    public ?string $statePath = null;

    #[On('open-choose-from-list')]
    public function open(string $source, string $statePath): void
    {
        // Throws for anything not in the registry — the key is user input.
        ChooseFromListRegistry::get($source);

        $this->sourceKey = $source;
        $this->statePath = $statePath;

        // Clear any search/sort/page left over from the previous field, so
        // opening the picker for Items never shows the Customers filter.
        $this->resetTable();

        $this->dispatch('open-modal', id: self::MODAL_ID);
    }

    public function table(Table $table): Table
    {
        $source = ChooseFromListRegistry::get($this->sourceKey);

        return $table
            ->query(fn (): Builder => ChooseFromListRegistry::query($this->sourceKey))
            ->columns($this->buildColumns($source['columns']))
            ->defaultSort($source['sort'])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->recordActions([
                Action::make('choose')
                    ->label('Choose')
                    ->link()
                    ->action(fn (Model $record) => $this->choose($record->getKey())),
            ])
            // Clicking anywhere on the row picks it, as in the SAP client.
            ->recordAction('choose')
            ->emptyStateHeading('No records found');
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<int, TextColumn>
     */
    protected function buildColumns(array $columns): array
    {
        return collect($columns)
            ->map(function (string $label, string $name) {
                $column = TextColumn::make($name)->label($label)->searchable()->sortable();

                // Numeric master-data columns read better right-aligned at 3 d.p.
                if (in_array($name, ['unit_price', 'qty_in_warehouse'], true)) {
                    $column->numeric(decimalPlaces: 3)->alignEnd();
                }

                return $column;
            })
            ->values()
            ->all();
    }

    public function choose(int|string $recordId): void
    {
        if ($this->statePath === null) {
            return;
        }

        $this->dispatch(
            'choose-from-list-selected',
            statePath: $this->statePath,
            recordId: $recordId,
            source: $this->sourceKey,
        );

        $this->dispatch('close-modal', id: self::MODAL_ID);
    }

    public function getHeading(): string
    {
        return ChooseFromListRegistry::get($this->sourceKey)['heading'];
    }

    public function render(): View
    {
        return view('livewire.choose-from-list');
    }
}
