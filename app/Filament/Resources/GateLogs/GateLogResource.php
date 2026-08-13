<?php

namespace App\Filament\Resources\GateLogs;

use App\Filament\Resources\GateLogs\Pages\ListGateLogs;
use App\Filament\Resources\GateLogs\Tables\GateLogsTable;
use App\Models\GateLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only audit trail of gate movements. Records are written by the Gate In
 * and Gate Out screens, never edited by hand.
 */
class GateLogResource extends Resource
{
    protected static ?string $model = GateLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Gate Operations';

    protected static ?string $navigationLabel = 'Gate Log';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return GateLogsTable::configure($table);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->role()->canOperateGate() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Badge showing how many vehicles are on site right now. */
    public static function getNavigationBadge(): ?string
    {
        $open = GateLog::query()->open()->count();

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
