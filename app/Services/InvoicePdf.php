<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Renders a document to PDF and files it against the invoice.
 *
 * The PDF is stored rather than streamed on demand so that what was sent to a
 * customer can be produced again unchanged — regenerating from live data would
 * quietly restate the document as it looks now.
 *
 * Rendering again replaces the file: the collection is singleFile, so an
 * invoice always has exactly one PDF and there is never a question of which one
 * is the document.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): Media
    {
        $invoice->loadMissing(['lines.item', 'customer', 'freightCharges']);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4');

        return $invoice
            ->addMediaFromString($pdf->output())
            ->usingFileName($this->filename($invoice))
            ->toMediaCollection(Invoice::PDF);
    }

    /**
     * Named after the document, so a folder of these is readable without
     * opening any of them.
     */
    public function filename(Invoice $invoice): string
    {
        $number = $invoice->document_number ?? $invoice->series.'-'.$invoice->doc_num;

        return str($number)->replace(['/', '\\', ' '], '-')->append('.pdf')->value();
    }
}
