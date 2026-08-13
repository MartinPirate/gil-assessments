<?php

namespace Database\Seeders;

use App\Models\VatCode;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Reference data the application cannot function without.
 *
 * Separated from the demo seeder because tests need these rows too: a document
 * cannot be priced without a VAT code, or stocked without a warehouse.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Kenyan VAT codes, matching the sample screen's O0 default.
        $vatCodes = [
            ['O0', 'Zero Rated / Out of Scope', 0, true],
            ['V16', 'Standard Rated 16%', 16, false],
            ['V8', 'Fuel / Petroleum 8%', 8, false],
            ['E', 'Exempt', 0, false],
        ];

        foreach ($vatCodes as [$code, $name, $rate, $isDefault]) {
            VatCode::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'rate' => $rate, 'is_default' => $isDefault, 'is_active' => true],
            );
        }

        $warehouses = [
            ['FG WHS', 'Finished Goods Warehouse', 'Nairobi', true],
            ['RM WHS', 'Raw Materials Warehouse', 'Nairobi', false],
            ['MSA WHS', 'Mombasa Depot', 'Mombasa', false],
        ];

        foreach ($warehouses as [$code, $name, $location, $isDefault]) {
            Warehouse::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'location' => $location, 'is_default' => $isDefault, 'is_active' => true],
            );
        }
    }
}
