<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\VatCode;
use App\Support\InvoiceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the A/R Invoice screen's form state into a persisted document.
 *
 * This lives outside the Livewire page so the same write path can be reached
 * from a console command, an import or a test without going through the UI,
 * and so the page stays a presentation concern.
 */
class InvoiceWriter
{
    public function __construct(
        protected DocumentNumberService $numbers,
        protected ApprovalService $approvals,
        protected OrderLifecycleService $lifecycle,
    ) {}

    /**
     * @param  array<string, mixed>  $state  the form state
     */
    public function store(array $state, int $userId, bool $asDraft = false): Invoice
    {
        $lines = collect($state['lines'] ?? [])
            ->filter(fn ($line) => is_array($line) && ! InvoiceCalculator::isBlankLine($line))
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'data.lines' => 'Add at least one invoice line.',
            ]);
        }

        // Resolve VAT rates server-side. A rate posted from the browser is
        // display state; using it would let a client set their own tax.
        $lines = $lines->map(fn (array $line) => $this->resolveLineTax($line));

        $totals = InvoiceCalculator::documentTotals(
            $lines->all(),
            (float) ($state['discount_percent'] ?? 0),
            (float) ($state['freight'] ?? 0),
            (float) ($state['total_down_payment'] ?? 0),
            0.0,                                   // nothing is applied at creation
            (bool) ($state['rounding_enabled'] ?? false),
        );

        $customer = Customer::findOrFail($state['customer_id']);
        $employee = SalesEmployee::find($state['sales_employee_id'] ?? null);

        $requiresApproval = ! $asDraft && $totals['document_total'] > Invoice::APPROVAL_THRESHOLD;

        return DB::transaction(function () use ($state, $lines, $totals, $customer, $employee, $userId, $asDraft, $requiresApproval) {
            // Reserved under a row lock in this same transaction, so a
            // concurrent save cannot be issued the same number.
            $docNum = $this->numbers->next(
                DocumentNumberService::AR_INVOICE,
                $state['series'] ?? 'IN',
            );

            $invoice = Invoice::create([
                'doc_num' => $docNum,
                'series' => $state['series'] ?? 'IN',
                'doc_type' => $asDraft ? Invoice::TYPE_DRAFT : Invoice::TYPE_INVOICE,

                'customer_id' => $customer->getKey(),
                // Snapshotted so later master-data edits cannot rewrite history.
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'contact_person' => $customer->contact_person,
                'kra_pin' => $customer->kra_pin,
                'currency' => $customer->currency,

                'posting_date' => $state['posting_date'],
                'value_date' => $state['value_date'] ?? $state['posting_date'],
                'document_date' => $state['document_date'] ?? $state['posting_date'],

                'sales_employee_id' => $employee?->getKey(),
                'sales_employee_name' => $employee?->name,
                'owner_id' => $userId,
                'owner_name' => \App\Models\User::find($userId)?->name,

                'summary_type' => $state['summary_type'] ?? 'No Summary',
                'payment_order_run' => (bool) ($state['payment_order_run'] ?? false),
                'remarks' => $state['remarks'],

                // Scanned off the ETR receipt, so it is only stamped when a
                // barcode was actually captured.
                'etr_barcode' => $etrBarcode = ($state['etr_barcode'] ?? null) ?: null,
                'etr_scanned_at' => $etrBarcode ? now() : null,

                'discount_percent' => (float) ($state['discount_percent'] ?? 0),
                'rounding_enabled' => (bool) ($state['rounding_enabled'] ?? false),
                ...$totals,
                // Nothing is paid yet, so the whole document is outstanding.
                'balance_due' => $asDraft ? 0 : $totals['document_total'],

                'requires_approval' => $requiresApproval,
                'status' => match (true) {
                    $asDraft => Invoice::STATUS_DRAFT,
                    $requiresApproval => Invoice::STATUS_PENDING_APPROVAL,
                    default => Invoice::STATUS_OPEN,
                },
                'created_by' => $userId,
            ]);

            foreach ($lines as $index => $line) {
                $this->storeLine($invoice, $line, $index + 1);
            }

            // Opened inside the same transaction: a document is never committed
            // as "Pending Approval" without a matching queue entry.
            $this->approvals->requestIfNeeded($invoice, $userId);

            /*
             * A draft is not an order yet — it carries no balance and may never
             * be posted — so the lifecycle starts only once the document is
             * real.
             */
            if (! $asDraft) {
                $this->lifecycle->record(
                    $invoice,
                    OrderStage::Placed,
                    $invoice->created_at,
                    User::find($userId),
                    "Invoice {$invoice->document_number} raised.",
                );
            }

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    protected function resolveLineTax(array $line): array
    {
        $vat = isset($line['vat_code_id']) ? VatCode::find($line['vat_code_id']) : null;
        $vat ??= VatCode::default();

        $line['vat_code_id'] = $vat?->getKey();
        $line['vat_code'] = $vat?->code;
        $line['vat_rate'] = (float) ($vat?->rate ?? 0);

        return InvoiceCalculator::recalculateLine($line);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function storeLine(Invoice $invoice, array $line, int $lineNum): InvoiceLine
    {
        $item = isset($line['item_id']) ? Item::find($line['item_id']) : null;

        return InvoiceLine::create([
            'invoice_id' => $invoice->getKey(),
            'line_num' => $lineNum,
            'item_service_type' => $line['item_service_type'] ?? 'Item',
            'item_id' => $item?->getKey(),
            'item_no' => $line['item_no'] ?? $item?->item_no,
            'item_description' => $line['item_description'] ?? $item?->description,
            'uom' => $line['uom'] ?? $item?->uom,
            'warehouse' => $line['warehouse'] ?? $item?->warehouse,
            'quantity' => (float) ($line['quantity'] ?? 0),
            'qty_in_warehouse' => (float) ($line['qty_in_warehouse'] ?? $item?->qty_in_warehouse ?? 0),
            'price_before_discount' => (float) ($line['price_before_discount'] ?? 0),
            'discount_percent' => (float) ($line['discount_percent'] ?? 0),
            'price_after_discount' => $line['price_after_discount'],
            'line_total' => $line['line_total'],
            'vat_code_id' => $line['vat_code_id'] ?? null,
            'vat_code' => $line['vat_code'] ?? null,
            'vat_rate' => $line['vat_rate'] ?? 0,
            'vat_amount' => $line['vat_amount'] ?? 0,
            'gross_price_after_discount' => $line['gross_price_after_discount'] ?? 0,
            'gross_total' => $line['gross_total'] ?? 0,
        ]);
    }
}
