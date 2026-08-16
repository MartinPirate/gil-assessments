@php
    $customers = \App\Models\Invoice::query()
        ->where('sales_employee_id', $getRecord()->getKey())
        ->where('doc_type', \App\Models\Invoice::TYPE_INVOICE)
        ->selectRaw('customer_id, customer_name, COUNT(*) AS documents, SUM(document_total) AS value')
        ->groupBy('customer_id', 'customer_name')
        ->orderByDesc('value')
        ->limit(15)
        ->get();
@endphp

@if ($customers->isEmpty())
    <p class="veh-empty">No posted documents yet, so no customers to show.</p>
@else
    <div class="veh-gate">
        <table class="veh-gate__table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Documents</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customers as $row)
                    <tr>
                        <td>{{ $row->customer_name }}</td>
                        <td>{{ $row->documents }}</td>
                        <td>KES {{ number_format((float) $row->value, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
