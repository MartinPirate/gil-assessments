<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whitelist of the lists a "Choose From List" button may open.
 *
 * The browser sends a key from this registry, never a model class or query.
 * Anything else is rejected, so the picker cannot be pointed at an arbitrary
 * table by editing the payload.
 */
class ChooseFromListRegistry
{
    /**
     * @return array<string, array{model: class-string, heading: string, columns: array<string, string>, searchable: array<int, string>, sort: string, query?: \Closure}>
     */
    public static function all(): array
    {
        return [
            // Customer list keyed by code — first column is the code.
            'customers_by_code' => [
                'model' => Customer::class,
                'heading' => 'List of Business Partners',
                'columns' => ['code' => 'Customer Code', 'name' => 'Customer Name', 'currency' => 'Currency'],
                'searchable' => ['code', 'name'],
                'sort' => 'code',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],

            // Same list, but the spec requires the name to lead this one.
            'customers_by_name' => [
                'model' => Customer::class,
                'heading' => 'List of Business Partners',
                'columns' => ['name' => 'Customer Name', 'code' => 'Customer Code', 'currency' => 'Currency'],
                'searchable' => ['name', 'code'],
                'sort' => 'name',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],

            'items' => [
                'model' => Item::class,
                'heading' => 'List of Items',
                'columns' => [
                    'item_no' => 'Item No.',
                    'description' => 'Item Description',
                    'uom' => 'UoM',
                    'unit_price' => 'Unit Price',
                    'qty_in_warehouse' => 'Qty in Whse',
                ],
                'searchable' => ['item_no', 'description'],
                'sort' => 'item_no',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],

            'sales_employees' => [
                'model' => SalesEmployee::class,
                'heading' => 'List of Sales Employees',
                'columns' => ['name' => 'Name', 'code' => 'Code', 'position' => 'Position'],
                'searchable' => ['name', 'code'],
                'sort' => 'name',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],

            'vehicles' => [
                'model' => Vehicle::class,
                'heading' => 'List of Vehicles',
                'columns' => ['vehicle_number' => 'Vehicle Number', 'make' => 'Make', 'vehicle_type' => 'Type'],
                'searchable' => ['vehicle_number', 'make'],
                'sort' => 'vehicle_number',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],

            // Only vehicles with an open gate-in record (Task 2c).
            'vehicles_gated_in' => [
                'model' => Vehicle::class,
                'heading' => 'Vehicles Currently Gated In',
                'columns' => ['vehicle_number' => 'Vehicle Number', 'make' => 'Make', 'vehicle_type' => 'Type'],
                'searchable' => ['vehicle_number', 'make'],
                'sort' => 'vehicle_number',
                'query' => fn (Builder $q) => $q->currentlyGatedIn(),
            ],

            'drivers' => [
                'model' => Driver::class,
                'heading' => 'List of Drivers',
                'columns' => ['name' => 'Driver Name', 'national_id' => 'Driver ID', 'phone' => 'Phone Number'],
                'searchable' => ['name', 'national_id', 'phone'],
                'sort' => 'name',
                'query' => fn (Builder $q) => $q->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array{model: class-string, heading: string, columns: array<string, string>, searchable: array<int, string>, sort: string, query?: \Closure}
     */
    public static function get(string $key): array
    {
        return self::all()[$key]
            ?? throw new \InvalidArgumentException("Unknown Choose From List source [{$key}].");
    }

    /**
     * Base query for a source, with its own filter already applied.
     */
    public static function query(string $key): Builder
    {
        $source = self::get($key);

        /** @var Builder $query */
        $query = $source['model']::query();

        if (isset($source['query'])) {
            $query = ($source['query'])($query);
        }

        return $query;
    }

    /**
     * Type-ahead search used by the Select fields, sharing the registry's
     * searchable columns so the dropdown and the modal never disagree.
     *
     * @return array<int|string, string>
     */
    public static function search(string $key, string $term, int $limit = 25): array
    {
        $source = self::get($key);
        $columns = array_keys($source['columns']);

        return self::query($key)
            ->where(function (Builder $q) use ($source, $term) {
                foreach ($source['searchable'] as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            })
            ->orderBy($source['sort'])
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn ($record) => [
                $record->getKey() => self::label($record, $columns),
            ])
            ->all();
    }

    /**
     * Label for a single record, e.g. "CC00001 — Walk In Customer - HQ".
     *
     * @param  array<int, string>  $columns
     */
    public static function label(mixed $record, array $columns): string
    {
        $primary = $columns[0] ?? null;
        $secondary = $columns[1] ?? null;

        $parts = array_filter([
            $primary ? (string) $record->{$primary} : null,
            $secondary ? (string) $record->{$secondary} : null,
        ], fn ($v) => filled($v));

        return implode('  —  ', $parts);
    }

    /**
     * Label for an already-selected id, used when the form is re-hydrated.
     *
     * Two columns are joined for the search results, where you are choosing
     * between rows and need to tell them apart. Once chosen, the field shows
     * only its own column — the Customer field carries the code and the Name
     * field the name, as the client does. Joining both there overflowed a
     * 10rem box and left the value truncated under the clear button.
     */
    public static function optionLabel(string $key, int|string $id, bool $primaryOnly = true): ?string
    {
        $source = self::get($key);
        $record = self::query($key)->find($id);

        if (! $record) {
            return null;
        }

        $columns = array_keys($source['columns']);

        return self::label($record, $primaryOnly ? array_slice($columns, 0, 1) : $columns);
    }
}
