<?php

namespace Tests\Feature;

use App\Enums\OrderStage;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\OrderStageEvent;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\OrderLifecycleService;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\TripService;
use App\Support\Timeline\OrderStageSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order lifecycle: placed, paid, dispatched, delivered, rated.
 */
class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'code' => 'CC00001', 'name' => 'Naivas Supermarket Ltd', 'currency' => 'KES',
        ]);
    }

    protected function invoice(float $total = 5000, int $docNum = 1): Invoice
    {
        return Invoice::create([
            'doc_num' => $docNum,
            'series' => 'IN',
            'doc_type' => Invoice::TYPE_INVOICE,
            'customer_id' => $this->customer->id,
            'customer_code' => $this->customer->code,
            'customer_name' => $this->customer->name,
            'currency' => 'KES',
            'posting_date' => now()->toDateString(),
            'remarks' => 'Test',
            'total_before_discount' => $total,
            'total_after_discount' => $total,
            'document_total' => $total,
            'balance_due' => $total,
            'status' => Invoice::STATUS_OPEN,
        ]);
    }

    protected function lifecycle(): OrderLifecycleService
    {
        return app(OrderLifecycleService::class);
    }

    public function test_a_stage_is_recorded_once_however_many_times_it_fires(): void
    {
        $invoice = $this->invoice();

        $first = $this->lifecycle()->record($invoice, OrderStage::Paid);
        $second = $this->lifecycle()->record($invoice, OrderStage::Paid);
        $third = $this->lifecycle()->record($invoice, OrderStage::Paid);

        $this->assertNotNull($first, 'The first call should record the stage.');
        $this->assertNull($second, 'A repeat call should report nothing to do.');
        $this->assertNull($third);

        $this->assertSame(1, OrderStageEvent::where('invoice_id', $invoice->id)->count());
    }

    /**
     * The unique index is the real guard, so it is asserted directly: a retry
     * of a Safaricom callback must not be able to write a second "paid".
     */
    public function test_the_database_refuses_a_duplicate_stage(): void
    {
        $invoice = $this->invoice();

        OrderStageEvent::create([
            'invoice_id' => $invoice->id,
            'stage' => OrderStage::Paid,
            'occurred_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        OrderStageEvent::create([
            'invoice_id' => $invoice->id,
            'stage' => OrderStage::Paid,
            'occurred_at' => now(),
        ]);
    }

    public function test_settling_an_invoice_records_the_paid_stage(): void
    {
        $invoice = $this->invoice(10000);

        $this->postJson('/api/mpesa/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RCT000001',
            'TransAmount' => '10000.00',
            'BillRefNumber' => 'IN-1',
            'MSISDN' => '254722345678',
            'FirstName' => 'Jane',
        ])->assertOk();

        $this->assertEqualsWithDelta(0, (float) $invoice->fresh()->balance_due, 0.001);
        $this->assertTrue($this->lifecycle()->hasReached($invoice, OrderStage::Paid));
    }

    /**
     * A part payment leaves a balance, so the order has not reached "paid".
     */
    public function test_a_part_payment_does_not_record_the_paid_stage(): void
    {
        $invoice = $this->invoice(10000);

        $this->postJson('/api/mpesa/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RCT000002',
            'TransAmount' => '4000.00',
            'BillRefNumber' => 'IN-1',
            'MSISDN' => '254722345678',
            'FirstName' => 'Jane',
        ])->assertOk();

        $this->assertGreaterThan(0, (float) $invoice->fresh()->balance_due);
        $this->assertFalse($this->lifecycle()->hasReached($invoice, OrderStage::Paid));
    }

    protected function tripFor(?Invoice $invoice): Trip
    {
        $route = Route::create([
            'code' => 'RT-NBO-MSA', 'name' => 'Nairobi - Mombasa',
            'origin' => 'Nairobi', 'destination' => 'Mombasa',
        ]);
        $vehicle = Vehicle::create(['vehicle_number' => 'KDA 001A', 'make' => 'Isuzu']);
        $driver = Driver::create(['name' => 'John Mwangi', 'national_id' => '12345678', 'phone' => '254700000001']);

        return Trip::create([
            'reference' => 'TRIP-0001',
            'invoice_id' => $invoice?->getKey(),
            'route_id' => $route->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'route_name' => $route->name,
            'vehicle_number' => $vehicle->vehicle_number,
            'driver_name' => $driver->name,
            'scheduled_at' => now(),
            'status' => Trip::STATUS_SCHEDULED,
        ]);
    }

    public function test_a_trip_carries_dispatch_and_delivery_onto_the_order(): void
    {
        $invoice = $this->invoice();
        $trip = $this->tripFor($invoice);
        $trips = app(TripService::class);

        $trips->depart($trip);
        $this->assertTrue($this->lifecycle()->hasReached($invoice, OrderStage::Dispatched));
        $this->assertFalse($this->lifecycle()->hasReached($invoice, OrderStage::Delivered));

        $trips->arrive($trip->fresh());
        $this->assertTrue($this->lifecycle()->hasReached($invoice, OrderStage::Delivered));
    }

    /**
     * A repositioning run carries no order, and must not blow up trying to
     * record a stage against one.
     */
    public function test_a_trip_without_an_order_records_nothing(): void
    {
        $trip = $this->tripFor(null);

        app(TripService::class)->depart($trip);

        $this->assertSame(0, OrderStageEvent::count());
    }

    public function test_a_cancelled_trip_does_not_advance_the_order(): void
    {
        $invoice = $this->invoice();
        $trip = $this->tripFor($invoice);

        app(TripService::class)->cancel($trip, 'Vehicle broke down');

        $this->assertSame(0, OrderStageEvent::where('invoice_id', $invoice->id)->count());
    }

    public function test_the_progress_position_tracks_the_furthest_stage_reached(): void
    {
        $invoice = $this->invoice();

        $this->assertSame(0, $this->lifecycle()->currentPosition($invoice));

        $this->lifecycle()->record($invoice, OrderStage::Placed);
        $this->assertSame(1, $this->lifecycle()->currentPosition($invoice));

        $this->lifecycle()->record($invoice, OrderStage::Dispatched);
        $this->assertSame(4, $this->lifecycle()->currentPosition($invoice));

        // Approval sits off the track and must not drag the position backwards.
        $this->lifecycle()->record($invoice, OrderStage::Approved);
        $this->assertSame(4, $this->lifecycle()->currentPosition($invoice));
    }

    public function test_the_timeline_source_returns_the_stages_for_an_order(): void
    {
        $invoice = $this->invoice();
        $other = $this->invoice(2000, 2);

        $this->lifecycle()->record($invoice, OrderStage::Placed);
        $this->lifecycle()->record($invoice, OrderStage::Paid);
        $this->lifecycle()->record($other, OrderStage::Placed);

        $result = (new OrderStageSource())->forRecord($invoice)->latestFirst(false)->paginate(10);

        $this->assertCount(2, $result->entries, 'Only this order\'s stages should be returned.');
        $this->assertSame('placed', $result->entries[0]->event);
        $this->assertSame('paid', $result->entries[1]->event);
        $this->assertSame('Paid', $result->entries[1]->title);
        $this->assertFalse($result->hasMore);
    }

    public function test_the_timeline_source_paginates(): void
    {
        $invoice = $this->invoice();

        foreach ([OrderStage::Placed, OrderStage::Paid, OrderStage::Dispatched] as $stage) {
            $this->lifecycle()->record($invoice, $stage);
        }

        $page = (new OrderStageSource())->forRecord($invoice)->latestFirst(false)->paginate(2);

        $this->assertCount(2, $page->entries);
        $this->assertTrue($page->hasMore);
        $this->assertNotNull($page->nextCursor);

        $next = (new OrderStageSource())->forRecord($invoice)->latestFirst(false)->paginate(2, $page->nextCursor);

        $this->assertCount(1, $next->entries);
        $this->assertFalse($next->hasMore);
    }

    /**
     * The lifecycle is only useful if the document page actually draws it.
     */
    public function test_the_invoice_page_renders_the_lifecycle(): void
    {
        $invoice = $this->invoice();
        $this->lifecycle()->record($invoice, OrderStage::Placed);
        $this->lifecycle()->record($invoice, OrderStage::Paid);

        $this->actingAs(\App\Models\User::factory()->role(\App\Enums\UserRole::Admin)->create());

        $this->get(InvoiceResource::getUrl('view', ['record' => $invoice]))
            ->assertSuccessful()
            ->assertSee('Order lifecycle')
            ->assertSee('Dispatched')      // a step still ahead of this order
            ->assertSee('order-track', escape: false);
    }

    /**
     * Configuration must not leak between timelines sharing a source instance.
     */
    public function test_configuring_the_source_does_not_mutate_the_original(): void
    {
        $invoice = $this->invoice();
        $source = new OrderStageSource();

        $configured = $source->forRecord($invoice);

        $this->assertNotSame($source, $configured);
        $this->assertTrue($source->paginate(5)->isEmpty(), 'The unscoped source should return nothing.');
    }
}
