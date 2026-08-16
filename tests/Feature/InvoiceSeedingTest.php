<?php

namespace Tests\Feature;

use App\Models\Invoice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The seeded register.
 *
 * Written through InvoiceWriter rather than inserted, so these assertions are
 * really about the write path holding up over a batch: numbers issued without
 * collision, the threshold applied, drafts kept out of the approval queue.
 */
class InvoiceSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_register_has_at_least_ten_documents(): void
    {
        $this->assertGreaterThanOrEqual(10, Invoice::count());
    }

    public function test_every_document_number_is_unique_within_its_series(): void
    {
        $numbers = Invoice::query()->get(['series', 'doc_num'])
            ->map(fn (Invoice $invoice) => $invoice->series.'-'.$invoice->doc_num);

        $this->assertSame($numbers->count(), $numbers->unique()->count());
    }

    public function test_documents_over_the_threshold_are_awaiting_approval(): void
    {
        $over = Invoice::query()
            ->where('doc_type', Invoice::TYPE_INVOICE)
            ->where('document_total', '>', Invoice::APPROVAL_THRESHOLD)
            ->get();

        $this->assertNotEmpty($over, 'The seed should include documents that breach the threshold.');

        foreach ($over as $invoice) {
            $this->assertTrue(
                $invoice->requires_approval,
                "{$invoice->document_number} is over the threshold and should be flagged.",
            );
        }
    }

    public function test_documents_under_the_threshold_are_open(): void
    {
        $under = Invoice::query()
            ->where('doc_type', Invoice::TYPE_INVOICE)
            ->where('document_total', '<=', Invoice::APPROVAL_THRESHOLD)
            ->get();

        $this->assertNotEmpty($under);

        foreach ($under as $invoice) {
            $this->assertFalse($invoice->requires_approval, "{$invoice->document_number} should not need approval.");
        }
    }

    public function test_every_posted_document_carries_its_pdf(): void
    {
        $posted = Invoice::where('doc_type', Invoice::TYPE_INVOICE)->get();

        $this->assertNotEmpty($posted);

        foreach ($posted as $invoice) {
            $this->assertTrue($invoice->hasPdf(), "{$invoice->document_number} should have a rendered PDF.");
        }
    }

    /**
     * A draft is not a document anybody should be sending out.
     */
    public function test_drafts_have_no_pdf_and_no_balance(): void
    {
        $drafts = Invoice::where('doc_type', Invoice::TYPE_DRAFT)->get();

        $this->assertNotEmpty($drafts);

        foreach ($drafts as $draft) {
            $this->assertFalse($draft->hasPdf());
            $this->assertEquals(0, $draft->balance_due);
        }
    }

    public function test_every_document_has_lines_that_add_up(): void
    {
        foreach (Invoice::with('lines')->get() as $invoice) {
            $this->assertNotEmpty($invoice->lines, "{$invoice->document_number} has no lines.");

            $this->assertEqualsWithDelta(
                (float) $invoice->lines->sum('line_total'),
                (float) $invoice->total_before_discount,
                0.01,
                "{$invoice->document_number} header does not match its lines.",
            );
        }
    }
}
