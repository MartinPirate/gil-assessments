<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Support\ChooseFromListRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The registry is a security surface: the browser sends the key that decides
 * which table the picker reads.
 */
class ChooseFromListRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Anything not on the whitelist must not resolve to a query.
        ChooseFromListRegistry::get('users');
    }

    public function test_every_registered_source_is_well_formed(): void
    {
        foreach (ChooseFromListRegistry::all() as $key => $source) {
            $this->assertArrayHasKey('model', $source, "{$key} needs a model");
            $this->assertArrayHasKey('columns', $source, "{$key} needs columns");
            $this->assertArrayHasKey('searchable', $source, "{$key} needs searchable columns");
            $this->assertTrue(class_exists($source['model']), "{$key} model must exist");
            $this->assertNotEmpty($source['columns'], "{$key} must show at least one column");

            // Every searchable column must be one the list actually displays,
            // or a user could filter on something they cannot see.
            foreach ($source['searchable'] as $column) {
                $this->assertArrayHasKey($column, $source['columns'], "{$key}: {$column} is searchable but not displayed");
            }
        }
    }

    public function test_search_matches_on_every_searchable_column(): void
    {
        Customer::create(['code' => 'CC00042', 'name' => 'Naivas Supermarket', 'currency' => 'KES']);

        $this->assertNotEmpty(ChooseFromListRegistry::search('customers_by_code', 'CC00042'));
        $this->assertNotEmpty(ChooseFromListRegistry::search('customers_by_code', 'Naivas'));
        $this->assertEmpty(ChooseFromListRegistry::search('customers_by_code', 'nothing-matches-this'));
    }

    /**
     * The spec asks for the customer name to lead its own list.
     */
    public function test_the_name_list_leads_with_the_name(): void
    {
        Customer::create(['code' => 'CC1', 'name' => 'Quickmart', 'currency' => 'KES']);

        $byName = array_values(ChooseFromListRegistry::search('customers_by_name', 'Quickmart'))[0];
        $byCode = array_values(ChooseFromListRegistry::search('customers_by_code', 'Quickmart'))[0];

        $this->assertStringStartsWith('Quickmart', $byName);
        $this->assertStringStartsWith('CC1', $byCode);
    }

    public function test_inactive_records_are_hidden(): void
    {
        Customer::create(['code' => 'CC2', 'name' => 'Closed Account', 'currency' => 'KES', 'is_active' => false]);

        $this->assertEmpty(ChooseFromListRegistry::search('customers_by_code', 'Closed'));
    }

    public function test_option_labels_resolve_and_degrade_safely(): void
    {
        $customer = Customer::create(['code' => 'CC3', 'name' => 'Carrefour', 'currency' => 'KES']);

        $this->assertStringContainsString('CC3', ChooseFromListRegistry::optionLabel('customers_by_code', $customer->id));
        // A deleted or filtered-out id must return null, not throw.
        $this->assertNull(ChooseFromListRegistry::optionLabel('customers_by_code', 999999));
    }

    public function test_search_is_bounded(): void
    {
        for ($i = 0; $i < 30; $i++) {
            Customer::create(['code' => 'BULK'.$i, 'name' => 'Bulk Customer '.$i, 'currency' => 'KES']);
        }

        // Unbounded results would be a memory risk on a large master file.
        $this->assertLessThanOrEqual(25, count(ChooseFromListRegistry::search('customers_by_code', 'Bulk')));
    }
}
