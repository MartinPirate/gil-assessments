<?php

namespace App\Filament\Resources\SalesEmployees;

use App\Filament\Resources\SalesEmployees\Pages\CreateSalesEmployee;
use App\Filament\Resources\SalesEmployees\Pages\EditSalesEmployee;
use App\Filament\Resources\SalesEmployees\Pages\ListSalesEmployees;
use App\Filament\Resources\SalesEmployees\Pages\ViewSalesEmployee;
use App\Filament\Resources\SalesEmployees\Schemas\SalesEmployeeForm;
use App\Filament\Resources\SalesEmployees\Tables\SalesEmployeesTable;
use App\Models\SalesEmployee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SalesEmployeeResource extends Resource
{
    protected static ?string $model = SalesEmployee::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->canAdminister() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SalesEmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesEmployeesTable::configure($table);
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
            'index' => ListSalesEmployees::route('/'),
            'create' => CreateSalesEmployee::route('/create'),
            'view' => ViewSalesEmployee::route('/{record}'),
            'edit' => EditSalesEmployee::route('/{record}/edit'),
        ];
    }
}
