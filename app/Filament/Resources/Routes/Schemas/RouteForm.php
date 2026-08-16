<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Models\Route;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Route')->columns(2)->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(32),
                TextInput::make('name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('origin')
                    ->required()
                    ->maxLength(150),
                TextInput::make('destination')
                    ->required()
                    ->maxLength(150),
                TextInput::make('estimated_hours')
                    ->label('Estimated hours')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9999)
                    ->step('0.25')
                    ->helperText('Half and quarter hours are fine — 9.5 for a nine-and-a-half hour run.'),
                Toggle::make('is_active')
                    ->required(),
            ]),

            Section::make('Where it runs')
                ->description('Click the map for the origin, click again for the destination, drag a pin to correct it.')
                ->schema([
                    View::make('filament.partials.route-map')->columnSpanFull(),

                    Grid::make(['default' => 2, 'lg' => 4])->schema([
                        /*
                         * Bounded, not merely numeric: a latitude of 500 is not
                         * a place, and a swapped pair silently relocates a
                         * Nairobi depot into the Indian Ocean.
                         */
                        TextInput::make('origin_latitude')
                            ->label('Origin lat')
                            ->numeric()->minValue(-90)->maxValue(90)->step('0.0000001')->live(),
                        TextInput::make('origin_longitude')
                            ->label('Origin lng')
                            ->numeric()->minValue(-180)->maxValue(180)->step('0.0000001')->live(),
                        TextInput::make('destination_latitude')
                            ->label('Destination lat')
                            ->numeric()->minValue(-90)->maxValue(90)->step('0.0000001')->live(),
                        TextInput::make('destination_longitude')
                            ->label('Destination lng')
                            ->numeric()->minValue(-180)->maxValue(180)->step('0.0000001')->live(),
                    ]),

                    /*
                     * The straight line between the two pins, offered rather
                     * than imposed: the road is always longer than the crow
                     * flies, so this is a floor. Someone who knows the actual
                     * distance should be able to keep their figure.
                     */
                    TextInput::make('distance_km')
                        ->label('Distance (km)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText(function (Get $get): string {
                            $straight = self::straightLine($get);

                            return $straight === null
                                ? 'Pin both ends on the map to see the straight-line distance.'
                                : sprintf('Straight line between the pins is %s km — the road will be longer.', $straight);
                        })
                        ->suffixAction(
                            Action::make('useStraightLine')
                                ->label('Use straight line')
                                ->icon('heroicon-o-arrow-path')
                                ->visible(fn (Get $get) => self::straightLine($get) !== null)
                                ->action(fn (Get $get, Set $set) => $set('distance_km', self::straightLine($get))),
                        ),
                ]),
        ]);
    }

    /**
     * Great-circle distance between whatever is currently in the four
     * coordinate fields.
     */
    protected static function straightLine(Get $get): ?float
    {
        $route = new Route([
            'origin_latitude' => $get('origin_latitude'),
            'origin_longitude' => $get('origin_longitude'),
            'destination_latitude' => $get('destination_latitude'),
            'destination_longitude' => $get('destination_longitude'),
        ]);

        return $route->greatCircleKm();
    }
}
