<?php

namespace App\Filament\Resources\Routes;

use App\Filament\Resources\Routes\Pages\CreateRoute;
use App\Filament\Resources\Routes\Pages\EditRoute;
use App\Filament\Resources\Routes\Pages\ListRoutes;
use App\Filament\Resources\Routes\Schemas\RouteForm;
use App\Filament\Resources\Routes\Tables\RoutesTable;
use App\Models\Route;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Routes';

    protected static ?int $navigationSort = 1;

    /**
     * Planners write routes; drivers read the ones they are sent down.
     *
     * A driver needs the leg they are driving — the stops, the distance, the
     * map — without being able to redraw it.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->canManageTrips() || ($user?->isDriver() && $user->driverId()));
    }

    /**
     * Whether this user draws routes, as opposed to driving them.
     *
     * One question asked in one place: the resource's own create/edit/delete
     * gates and the buttons on the list screen all read it, because Filament
     * does not wire header and row actions to the resource's gates by itself —
     * a New route button that 403s on click is worse than no button.
     */
    public static function canPlan(): bool
    {
        return Auth::user()?->canManageTrips() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canPlan();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canPlan();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canPlan();
    }

    public static function canDeleteAny(): bool
    {
        return static::canPlan();
    }

    /**
     * A driver sees the routes their own trips run on, and no others.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if (! $user || $user->canManageTrips()) {
            return $query;
        }

        return $query->whereHas(
            'trips',
            fn (Builder $trips) => $trips->forDriver($user->driverId()),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return RouteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoutesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoutes::route('/'),
            'create' => CreateRoute::route('/create'),
            'edit' => EditRoute::route('/{record}/edit'),
        ];
    }
}
