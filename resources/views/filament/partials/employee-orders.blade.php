@php
    $invoices = \App\Models\Invoice::query()
        ->where('sales_employee_id', $getRecord()->getKey())
        ->latest('posting_date')
        ->limit(25)
        ->get();
@endphp

@if ($invoices->isEmpty())
    <p class="veh-empty">This salesperson has not raised a document yet.</p>
@else
    <div class="veh-gate">
        <table class="veh-gate__table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->document_number }}</td>
                        <td>{{ $invoice->posting_date?->format('d/m/Y') }}</td>
                        <td>{{ $invoice->customer_name }}</td>
                        <td>KES {{ number_format((float) $invoice->document_total, \App\Support\InvoiceCalculator::DOCUMENT_SCALE) }}</td>
                        <td>KES {{ number_format((float) $invoice->balance_due, \App\Support\InvoiceCalculator::DOCUMENT_SCALE) }}</td>
                        <td>{{ $invoice->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
