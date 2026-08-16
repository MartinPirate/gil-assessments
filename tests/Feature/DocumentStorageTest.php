<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoicePdf;
use App\Services\InvoiceWriter;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Files kept against records: a driver's licence, and an invoice's PDF.
 */
class DocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Media goes to a fake disk so a test run leaves nothing behind.
        Storage::fake('public');
        Storage::fake('local');
    }

    /* -----------------------------------------------------------------
     | Driver licences
     | ----------------------------------------------------------------- */

    public function test_a_driver_keeps_one_licence_on_file(): void
    {
        $driver = Driver::factory()->create();

        $this->assertFalse($driver->hasLicence());

        $driver->addMedia(UploadedFile::fake()->image('licence.jpg'))
            ->toMediaCollection(Driver::LICENCE);

        $this->assertTrue($driver->fresh()->hasLicence());
        $this->assertSame('licence.jpg', $driver->fresh()->licence()->file_name);
    }

    /**
     * A driver has one current licence. Uploading a new scan must replace the
     * old one rather than leaving two on file for somebody to read the wrong
     * one of.
     */
    public function test_a_new_licence_replaces_the_previous_one(): void
    {
        $driver = Driver::factory()->create();

        $driver->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(Driver::LICENCE);
        $driver->refresh();
        $driver->addMedia(UploadedFile::fake()->image('new.jpg'))->toMediaCollection(Driver::LICENCE);

        $driver->refresh();

        $this->assertCount(1, $driver->getMedia(Driver::LICENCE));
        $this->assertSame('new.jpg', $driver->licence()->file_name);
    }

    /* -----------------------------------------------------------------
     | Invoice PDFs
     | ----------------------------------------------------------------- */

    protected function anInvoice(): Invoice
    {
        $this->seed(ReferenceDataSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::create(['code' => 'CC1', 'name' => 'Naivas Supermarket Ltd', 'currency' => 'KES']);
        $customer->contactPeople()->create(['name' => 'Jane Wanjiru']);

        $item = Item::create([
            'item_no' => 'FG00011',
            'description' => 'Umi All Purpose Home Baking Flour 2Kg',
            'uom' => 'Bales',
            'warehouse_id' => Warehouse::where('code', 'FG WHS')->value('id'),
            'unit_price' => 1850,
            'qty_in_warehouse' => 648,
        ]);

        return app(InvoiceWriter::class)->store([
            'customer_id' => $customer->getKey(),
            'posting_date' => now()->toDateString(),
            'series' => 'IN',
            'remarks' => 'Seeded for the PDF test',
            'lines' => [[
                'item_id' => $item->getKey(),
                'item_description' => $item->description,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => 2,
                'price_before_discount' => 1850,
                'discount_percent' => 0,
            ]],
        ], $user->getKey());
    }

    public function test_rendering_an_invoice_files_a_pdf_against_it(): void
    {
        $invoice = $this->anInvoice();

        $this->assertFalse($invoice->hasPdf());

        $media = app(InvoicePdf::class)->render($invoice);

        $invoice->refresh();

        $this->assertTrue($invoice->hasPdf());
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertStringEndsWith('.pdf', $media->file_name);
        // Named after the document, so a folder of these reads without opening
        // any of them.
        $this->assertStringContainsString($invoice->doc_num, $media->file_name);
        $this->assertGreaterThan(0, $media->size);
    }

    /**
     * The collection is singleFile, so an invoice always has exactly one PDF
     * and there is never a question of which one is the document.
     */
    public function test_rendering_again_replaces_the_pdf(): void
    {
        $invoice = $this->anInvoice();
        $pdf = app(InvoicePdf::class);

        $pdf->render($invoice);
        $invoice->refresh();
        $pdf->render($invoice);
        $invoice->refresh();

        $this->assertCount(1, $invoice->getMedia(Invoice::PDF));
    }

    /**
     * Attachments are a separate collection because they answer a different
     * question — what came back, rather than what was produced.
     */
    public function test_attachments_do_not_disturb_the_rendered_document(): void
    {
        $invoice = $this->anInvoice();

        app(InvoicePdf::class)->render($invoice);
        $invoice->refresh();

        // Real bytes, not an empty fake: the collection sniffs the mime type
        // rather than trusting the extension, and an empty file reads as
        // application/x-empty and is refused — which is the guard working.
        $invoice->addMedia(UploadedFile::fake()->createWithContent(
            'signed-delivery-note.pdf',
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n",
        ))->toMediaCollection(Invoice::ATTACHMENTS);

        $invoice->refresh();

        $this->assertCount(1, $invoice->getMedia(Invoice::PDF));
        $this->assertCount(1, $invoice->getMedia(Invoice::ATTACHMENTS));
        $this->assertTrue($invoice->hasPdf());
    }
}
