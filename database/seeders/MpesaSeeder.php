<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Services\MpesaC2BService;
use App\Services\PaymentAllocationService;
use Illuminate\Database\Seeder;

/**
 * Receipts arriving on the C2B endpoint, and what they settle.
 *
 * Pushed through MpesaC2BService and PaymentAllocationService rather than
 * inserted, so each one goes the way a real callback does: parsed, stored
 * against (trans_id, callback_type), matched to a document by its BillRefNumber
 * and allocated — which is what closes the invoice and moves the order on.
 * Inserting rows would leave a payments screen that looks settled and a
 * register that disagrees with it.
 *
 * The mix is deliberate: some invoices paid in full, one part-paid, and two
 * receipts quoting a reference that matches nothing, because unmatched money
 * arriving is the case the reconciliation screen exists for.
 */
class MpesaSeeder extends Seeder
{
    public function run(): void
    {
        if (MpesaTransaction::whereNotNull('bill_ref_number')->count() >= 6) {
            return;
        }

        $c2b = app(MpesaC2BService::class);
        $allocator = app(PaymentAllocationService::class);

        $payable = Invoice::query()
            ->where('doc_type', Invoice::TYPE_INVOICE)
            ->where('balance_due', '>', 0)
            ->orderBy('id')
            ->take(5)
            ->get();

        $payers = [
            ['Jane', 'W', 'Wanjiru', '254711234001'],
            ['Peter', 'O', 'Otieno', '254722345001'],
            ['Aisha', 'M', 'Mohamed', '254733456001'],
            ['Rajesh', 'K', 'Patel', '254744567001'],
            ['Brian', 'N', 'Kimani', '254755678001'],
        ];

        foreach ($payable as $index => $invoice) {
            [$first, $middle, $last, $msisdn] = $payers[$index % count($payers)];

            // The fourth is a part payment: a customer paying what they can is
            // ordinary, and the balance has to survive it.
            $amount = $index === 3
                ? round((float) $invoice->balance_due / 2, 2)
                : (float) $invoice->balance_due;

            $transaction = $c2b->store([
                'TransactionType' => 'Pay Bill',
                'TransID' => 'SEED'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'TransTime' => now()->subDays(6 - $index)->format('YmdHis'),
                'TransAmount' => number_format($amount, 2, '.', ''),
                'BusinessShortCode' => '600100',
                'BillRefNumber' => $invoice->document_number,
                'MSISDN' => $msisdn,
                'FirstName' => $first,
                'MiddleName' => $middle,
                'LastName' => $last,
            ]);

            /*
             * store() is keyed on (trans_id, callback_type), so re-running the
             * seeder returns the same receipt rather than a new one — and a
             * receipt that has already been applied has nothing left to give.
             */
            if ($transaction->allocations()->exists()) {
                continue;
            }

            $allocator->autoAllocate($transaction);
        }

        /*
         * Two that match nothing. Money does arrive quoting a reference nobody
         * recognises, and a reconciliation screen with no unmatched receipts on
         * it has never been tested against the case it exists for.
         */
        foreach ([['SEED000090', 'IN-99999999', '254700111222'], ['SEED000091', 'DEPOSIT', '254700333444']] as $index => [$id, $ref, $msisdn]) {
            $c2b->store([
                'TransactionType' => 'Pay Bill',
                'TransID' => $id,
                'TransTime' => now()->subDays($index + 1)->format('YmdHis'),
                'TransAmount' => '5000.00',
                'BusinessShortCode' => '600100',
                'BillRefNumber' => $ref,
                'MSISDN' => $msisdn,
                'FirstName' => 'Unknown',
                'MiddleName' => '',
                'LastName' => 'Payer',
            ]);
        }
    }
}
