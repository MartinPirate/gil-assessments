<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\VatCode;
use App\Services\InvoicePdf;
use App\Services\InvoiceWriter;
use Illuminate\Database\Seeder;

/**
 * A register with something in it.
 *
 * Written through InvoiceWriter rather than by inserting rows, so every
 * document here went down the same path a user's would: numbers issued under a
 * lock, VAT resolved server-side, totals recalculated, the approval threshold
 * applied and the order lifecycle opened. Seeding the rows directly would
 * produce a register that looks right and proves nothing.
 *
 * The mix is deliberate — most sit under the 10,000 approval threshold, four
 * breach it and land in the approval queue, and two are drafts.
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        // Re-running must not stack a second set on top of the first.
        if (Invoice::count() >= 10) {
            return;
        }

        $author = User::where('email', 'sales@gil.test')->first()
            ?? User::query()->firstOrFail();

        $customers = Customer::orderBy('id')->get();
        $items = Item::orderBy('id')->get();
        $employees = SalesEmployee::orderBy('id')->get();

        if ($customers->isEmpty() || $items->isEmpty()) {
            return;
        }

        $pdf = app(InvoicePdf::class);

        // Looked up once: freight charges name their VAT code, not its id.
        $vatCodes = VatCode::pluck('id', 'code');

        /*
         * [customer index, [[item index, qty], ...], days ago, remarks, draft,
         *  [[freight description, amount, vat code], ...]]
         *
         * Freight only on the runs that would really carry it: the long-haul
         * bulk orders and the two out-of-town deliveries. A counter sale is
         * carried out of the door, so it has none.
         */
        $documents = [
            [1, [[0, 4], [4, 2]], 26, 'Westlands branch restock.', false, []],
            [2, [[1, 6]], 24, 'Thindigua weekly order.', false, [['Delivery', 1800, 'V16']]],
            [3, [[0, 20], [2, 8]], 22, 'Two Rivers month-end bulk order.', false, [['Delivery', 4500, 'V16'], ['Insurance', 1200, 'O0']]],
            [4, [[5, 3]], 20, 'Yaya Centre top-up.', false, []],
            [0, [[8, 2]], 18, 'Counter sale, walk-in.', false, []],
            [5, [[7, 12], [3, 6]], 16, 'Nyamakima wholesale run.', false, [['Delivery', 3200, 'V16'], ['Packing', 450, 'V16']]],
            [6, [[4, 5], [6, 2]], 14, 'Kayole Junction delivery.', false, [['Delivery', 1500, 'V16']]],
            [1, [[9, 9], [0, 10]], 12, 'Naivas festive season build-up.', false, [['Delivery', 5200, 'V16'], ['Insurance', 2000, 'O0']]],
            [7, [[2, 3]], 10, 'Kikuyu branch trial order.', false, [['Delivery', 2400, 'V16']]],
            [3, [[0, 15], [1, 15], [4, 10]], 8, 'Carrefour quarterly contract call-off.', false, [['Delivery', 6800, 'V16'], ['Insurance', 2500, 'O0'], ['Packing', 900, 'V16']]],
            [2, [[6, 4]], 6, 'Quickmart porridge mix promo.', false, []],
            [4, [[3, 2]], 4, 'Chandarana sample order.', false, []],
            [5, [[8, 6]], 3, 'Draft — awaiting confirmation of quantities.', true, []],
            [6, [[5, 4]], 2, 'Draft — pending customer PO.', true, []],
        ];

        foreach ($documents as [$customerIndex, $lines, $daysAgo, $remarks, $isDraft, $freight]) {
            $customer = $customers[$customerIndex % $customers->count()];
            $employee = $employees->isEmpty() ? null : $employees[$customerIndex % $employees->count()];
            $date = now()->subDays($daysAgo)->toDateString();

            $invoice = app(InvoiceWriter::class)->store([
                'series' => 'IN',
                'customer_id' => $customer->getKey(),
                'sales_employee_id' => $employee?->getKey(),
                'posting_date' => $date,
                'value_date' => $date,
                'document_date' => $date,
                'remarks' => $remarks,
                'discount_percent' => 0,
                'freight_charges' => collect($freight)
                    ->map(fn (array $charge) => [
                        'description' => $charge[0],
                        'amount' => $charge[1],
                        // The rate is resolved from the code by InvoiceWriter,
                        // so only the code is named here.
                        'vat_code_id' => $vatCodes[$charge[2]] ?? null,
                    ])
                    ->all(),
                'lines' => collect($lines)
                    ->map(function (array $line) use ($items): array {
                        $item = $items[$line[0] % $items->count()];

                        return [
                            'item_id' => $item->getKey(),
                            'item_description' => $item->description,
                            'warehouse_id' => $item->warehouse_id,
                            'quantity' => $line[1],
                            'price_before_discount' => (float) $item->unit_price,
                            'discount_percent' => 0,
                        ];
                    })
                    ->all(),
            ], $author->getKey(), $isDraft);

            // A draft is not a document anybody should be sending out, so it
            // does not get a rendered PDF.
            if (! $isDraft) {
                $pdf->render($invoice);
            }
        }
    }
}
