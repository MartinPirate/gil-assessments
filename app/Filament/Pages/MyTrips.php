<?php

namespace App\Filament\Pages;

use App\Models\GateLog;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The driver's own screen.
 *
 * Everything here is scoped to the signed-in driver's own record. A driver must
 * never see another driver's work, so the scoping is applied in the query
 * rather than being left to the view.
 */
class MyTrips extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'My Trips';

    protected static string|\UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'My Trips';

    protected string $view = 'filament.pages.my-trips';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // Admins can open it to see what a driver sees, but only if their own
        // account is linked to a driver record.
        return (bool) ($user?->role()->isDriver() && $user->driverId());
    }

    public static function getNavigationBadge(): ?string
    {
        $driverId = Auth::user()?->driverId();

        if (! $driverId) {
            return null;
        }

        $open = Trip::query()->forDriver($driverId)->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Trip>
     */
    public function getTrips()
    {
        return Trip::query()
            ->forDriver(Auth::user()?->driverId())
            ->with('route')
            ->orderByRaw("CASE status WHEN 'In Transit' THEN 0 WHEN 'Scheduled' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, GateLog>
     */
    public function getRecentGateLogs()
    {
        return GateLog::query()
            ->where('driver_id', Auth::user()?->driverId() ?? 0)
            ->latest('time_in')
            ->limit(10)
            ->get();
    }

    /**
     * Filament actions rather than hand-rolled wire:click buttons: the action
     * machinery owns the request lifecycle, loading state and notifications,
     * and the trip id travels as an action argument.
     */
    public function startTripAction(): Action
    {
        return Action::make('startTrip')
            ->label('Start trip')
            ->color('info')
            ->size('lg')
            // No confirmation step: a driver taps this on a phone at the
            // gate, and the action is reversible by an admin if mis-tapped.
            ->action(function (array $arguments) {
                $trip = $this->ownedTrip((int) $arguments['trip']);

                app(TripService::class)->depart($trip);

                Notification::make()->title("{$trip->reference} started")->success()->send();
            });
    }

    public function completeTripAction(): Action
    {
        return Action::make('completeTrip')
            ->label('Mark arrived')
            ->color('success')
            ->size('lg')
            // No confirmation step: a driver taps this on a phone at the
            // gate, and the action is reversible by an admin if mis-tapped.
            ->action(function (array $arguments) {
                $trip = $this->ownedTrip((int) $arguments['trip']);

                app(TripService::class)->arrive($trip);

                Notification::make()->title("{$trip->reference} completed")->success()->send();
            });
    }

    /**
     * Re-checks ownership server-side.
     *
     * The trip id arrives from the browser as an action argument, so a driver
     * must not be able to act on someone else's trip by editing it. Scoping by
     * driver *before* the key lookup means a foreign id 404s rather than
     * resolving.
     */
    protected function ownedTrip(int $tripId): Trip
    {
        return Trip::query()
            ->forDriver(Auth::user()?->driverId())
            ->whereKey($tripId)
            ->firstOrFail();
    }

    public function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Refresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->action(fn () => null);
    }
}
