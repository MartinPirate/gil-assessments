<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\GateLog;
use App\Services\GateService;
use App\Filament\Concerns\InteractsWithChooseFromList;
use App\Support\ChooseFromListRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Task 2b — Vehicle Gate In.
 *
 * Responsive by construction: the form is a single column on a phone and two
 * columns from the medium breakpoint up, with larger touch targets on small
 * screens (see .gate-page in the theme).
 */
class VehicleGateIn extends Page implements HasForms
{
    use InteractsWithChooseFromList;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static ?string $navigationLabel = 'Gate In';

    protected static string|\UnitEnum|null $navigationGroup = 'Gate Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Vehicle Gate In';

    protected string $view = 'filament.pages.vehicle-gate-in';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canOperateGate() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Vehicle & Driver')
                    ->description('Date, time and the recording user are captured automatically on save.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle Number')
                                ->placeholder('Search a vehicle…')
                                ->searchable()
                                ->required()
                                ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('vehicles', $search))
                                ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('vehicles', $value))
                                ->suffixAction($this->chooseFromListAction('vehicles'))
                                ->live()
                                ->afterStateUpdated(fn (?string $state) => $this->warnIfAlreadyInside($state)),

                            Select::make('driver_id')
                                ->label('Driver Name')
                                ->placeholder('Search a driver…')
                                ->searchable()
                                ->required()
                                ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('drivers', $search))
                                ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('drivers', $value))
                                ->suffixAction($this->chooseFromListAction('drivers'))
                                ->live()
                                ->afterStateUpdated(fn (?string $state, Set $set) => $this->applyDriver($state, $set)),

                            TextInput::make('driver_national_id')
                                ->label('Driver ID')
                                ->required()
                                ->maxLength(32)
                                ->live(onBlur: true),

                            TextInput::make('driver_phone')
                                ->label('Phone Number')
                                ->tel()
                                ->required()
                                ->maxLength(32)
                                ->live(onBlur: true),
                        ]),

                        Textarea::make('gate_in_remarks')
                            ->label('Remarks')
                            ->rows(2)
                            ->maxLength(500)
                            ->live(onBlur: true),
                    ]),
            ]);
    }

    #[On('choose-from-list-selected')]
    public function chooseFromListSelected(string $statePath, int|string $recordId, string $source): void
    {
        if (! $this->isWritableStatePath($statePath)) {
            return;
        }

        data_set($this, $statePath, (string) $recordId);

        match ($source) {
            'drivers' => $this->applyDriver((string) $recordId, $this->siblingSetter($statePath)),
            'vehicles' => $this->warnIfAlreadyInside((string) $recordId),
            default => null,
        };
    }

    /**
     * Auto-fill ID and phone from the driver master.
     *
     * @param  Set|callable  $set
     */
    protected function applyDriver(?string $driverId, $set): void
    {
        $driver = $driverId ? Driver::find($driverId) : null;

        $set('driver_national_id', $driver?->national_id);
        $set('driver_phone', $driver?->phone);
    }

    /**
     * Tell the officer immediately rather than at save time; the service still
     * enforces this under a lock when the record is written.
     */
    protected function warnIfAlreadyInside(?string $vehicleId): void
    {
        if (! $vehicleId) {
            return;
        }

        $open = app(GateService::class)->openLogFor((int) $vehicleId);

        if ($open) {
            Notification::make()
                ->title('Vehicle already on site')
                ->body("{$open->vehicle_number} was gated in at {$open->time_in->format('d/m/Y H:i')} and has not gated out.")
                ->warning()
                ->send();
        }
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Record Gate In')
            ->icon(Heroicon::OutlinedCheck)
            ->submit('save');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $log = app(GateService::class)->gateIn($state, Auth::id());

        Notification::make()
            ->title('Gate in recorded')
            ->body("{$log->vehicle_number} — {$log->driver_name} at {$log->time_in->format('d/m/Y H:i')}")
            ->success()
            ->send();

        $this->form->fill();
    }

    /**
     * Vehicles currently on site, shown as a live list beneath the form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, GateLog>
     */
    public function getVehiclesOnSite()
    {
        return GateLog::query()
            ->open()
            ->with('vehicle')
            ->latest('time_in')
            ->limit(10)
            ->get();
    }
}
