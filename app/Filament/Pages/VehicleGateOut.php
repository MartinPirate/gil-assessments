<?php

namespace App\Filament\Pages;

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
 * Task 2c — Vehicle Gate Out.
 *
 * The vehicle list is deliberately NOT the full fleet: it only offers vehicles
 * with an open gate-in record, and the driver details are read back from that
 * record rather than re-entered.
 */
class VehicleGateOut extends Page implements HasForms
{
    use InteractsWithChooseFromList;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowLeftOnRectangle;

    protected static ?string $navigationLabel = 'Gate Out';

    protected static string|\UnitEnum|null $navigationGroup = 'Gate Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Vehicle Gate Out';

    protected string $view = 'filament.pages.vehicle-gate-out';

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
                Section::make('Vehicle Leaving')
                    ->description('Only vehicles currently gated in are listed. Time out and the recording user are captured automatically.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle Number')
                                ->placeholder('Search vehicles on site…')
                                ->searchable()
                                ->required()
                                // Registry key filters to open gate-in records.
                                ->getSearchResultsUsing(fn (string $search) => ChooseFromListRegistry::search('vehicles_gated_in', $search))
                                ->getOptionLabelUsing(fn ($value) => ChooseFromListRegistry::optionLabel('vehicles_gated_in', $value))
                                ->suffixAction($this->chooseFromListAction('vehicles_gated_in'))
                                ->live()
                                ->afterStateUpdated(fn (?string $state, Set $set) => $this->applyOpenLog($state, $set)),

                            TextInput::make('driver_name')
                                ->label('Driver Name')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('driver_national_id')
                                ->label('Driver ID')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('driver_phone')
                                ->label('Phone Number')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('time_in_display')
                                ->label('Time In')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('duration_display')
                                ->label('Time On Site')
                                ->disabled()
                                ->dehydrated(false),
                        ]),

                        Textarea::make('gate_out_remarks')
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

        if ($source === 'vehicles_gated_in') {
            $this->applyOpenLog((string) $recordId, $this->siblingSetter($statePath));
        }
    }

    /**
     * Task 2c: driver details auto-populate from the open gate-in record.
     *
     * @param  Set|callable  $set
     */
    protected function applyOpenLog(?string $vehicleId, $set): void
    {
        $log = $vehicleId ? app(GateService::class)->openLogFor((int) $vehicleId) : null;

        $set('driver_name', $log?->driver_name);
        $set('driver_national_id', $log?->driver_national_id);
        $set('driver_phone', $log?->driver_phone);
        $set('time_in_display', $log?->time_in?->format('d/m/Y H:i'));
        $set('duration_display', $log?->duration);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Record Gate Out')
            ->icon(Heroicon::OutlinedCheck)
            ->submit('save');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $log = app(GateService::class)->gateOut(
            (int) $state['vehicle_id'],
            Auth::id(),
            $state['gate_out_remarks'] ?? null,
        );

        Notification::make()
            ->title('Gate out recorded')
            ->body("{$log->vehicle_number} left at {$log->time_out->format('d/m/Y H:i')} after {$log->duration}.")
            ->success()
            ->send();

        $this->form->fill();
    }

    /**
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
