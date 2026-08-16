<?php

namespace App\Filament\Resources\GateLogs;

use App\Filament\Resources\GateLogs\Pages\ListGateLogs;
use App\Filament\Resources\GateLogs\Tables\GateLogsTable;
use App\Models\GateLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Read-only audit trail of gate movements. Records are written by the Gate In
 * and Gate Out screens, never edited by hand.
 */
class GateLogResource extends Resource
{
    protected static ?string $model = GateLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Gate Operations';

    protected static ?string $navigationLabel = 'Gate Log';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return GateLogsTable::configure($table);
    }

    /**
     * The gate officer reads the whole log; a driver reads their own line of it.
     *
     * A driver being turned away from the record of their own movements is the
     * one thing here nobody can justify — they were the person at the barrier.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->canOperateGate() || ($user?->isDriver() && $user->driverId()));
    }

    /**
     * A driver sees only their own crossings.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if (! $user || $user->canOperateGate()) {
            return $query;
        }

        // Falls back to 0 rather than to an unscoped query: a driver account
        // with no driver record must see nothing, not everything.
        return $query->where('driver_id', $user->driverId() ?? 0);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Badge showing how many vehicles are on site right now — counted through
     * the same query as the table, so a driver's badge cannot advertise
     * movements they are not allowed to open.
     */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGateLogs::route('/'),
        ];
    }
}
