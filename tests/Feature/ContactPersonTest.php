<?php

namespace Tests\Feature;

use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceWriter;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contact people, and the customer's default among them.
 */
class ContactPersonTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_keeps_several_contacts_each_with_a_way_to_reach_them(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Naivas', 'currency' => 'KES']);

        $customer->contactPeople()->create([
            'name' => 'Jane Wanjiru',
            'email' => 'jane@naivas.test',
            'phone' => '+254711234001',
        ]);
        $customer->contactPeople()->create([
            'name' => 'Kevin Omondi',
            'email' => 'kevin@naivas.test',
            'phone' => '+254711234002',
        ]);

        $this->assertCount(2, $customer->contactPeople);
        $this->assertSame('jane@naivas.test', $customer->contactPeople->first()->email);
        $this->assertSame('+254711234001', $customer->contactPeople->first()->phone);
    }

    public function test_the_first_contact_becomes_the_default(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Quickmart', 'currency' => 'KES']);

        $this->assertNull($customer->contact_person_id);

        $first = $customer->contactPeople()->create(['name' => 'Peter Otieno']);
        $customer->contactPeople()->create(['name' => 'Someone Else']);

        $this->assertTrue($first->is($customer->fresh()->contactPerson));
    }

    public function test_deleting_the_default_hands_the_role_to_whoever_is_left(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Carrefour', 'currency' => 'KES']);

        $first = $customer->contactPeople()->create(['name' => 'Aisha Mohamed']);
        $second = $customer->contactPeople()->create(['name' => 'Linda Chebet']);

        $first->delete();

        $this->assertTrue($second->is($customer->fresh()->contactPerson));
    }

    public function test_deleting_the_last_contact_leaves_the_customer_without_one(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Eastmatt', 'currency' => 'KES']);
        $only = $customer->contactPeople()->create(['name' => 'Grace Achieng']);

        $only->delete();

        $this->assertNull($customer->fresh()->contact_person_id);
    }

    public function test_deleting_a_customer_takes_its_contacts_with_it(): void
    {
        $customer = Customer::create(['code' => 'CC1', 'name' => 'Tuskys', 'currency' => 'KES']);
        $customer->contactPeople()->create(['name' => 'Brian Kimani']);
        $customer->contactPeople()->create(['name' => 'Second Person']);

        $customerId = $customer->getKey();
        $customer->delete();

        $this->assertSame(0, ContactPerson::where('customer_id', $customerId)->count());
    }

    /**
     * The document snapshots the name, so correcting master data later must
     * not rewrite an invoice that was already issued.
     */
    public function test_an_invoice_snapshots_the_contact_name_at_the_time_it_was_raised(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->seed(ReferenceDataSeeder::class);

        $customer = Customer::create(['code' => 'CC1', 'name' => 'Chandarana', 'currency' => 'KES']);
        $contact = $customer->contactPeople()->create(['name' => 'Rajesh Patel']);

        $item = Item::create([
            'item_no' => 'FG00011',
            'description' => 'Umi All Purpose Home Baking Flour 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
            'unit_price' => 1850,
            'qty_in_warehouse' => 648,
        ]);

        $invoice = app(InvoiceWriter::class)->store([
            'customer_id' => $customer->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => '',
            'lines' => [[
                'item_id' => $item->getKey(),
                'quantity' => 1,
                'unit_price' => 1850,
            ]],
        ], $user->getKey());

        $this->assertSame('Rajesh Patel', $invoice->contact_person);

        $contact->update(['name' => 'Rajesh Patel (left the company)']);

        $this->assertSame('Rajesh Patel', $invoice->fresh()->contact_person);
        $this->assertInstanceOf(Invoice::class, $invoice);
    }
}
