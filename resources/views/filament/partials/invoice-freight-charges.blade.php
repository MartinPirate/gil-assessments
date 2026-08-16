@php
    /** @var \App\Models\Invoice $invoice */
    $invoice = $getRecord();
    $charges = $invoice->freightCharges()->with('vatCode')->get();
    $money = fn ($value) => number_format((float) $value, \App\Support\InvoiceCalculator::DOCUMENT_SCALE);
@endphp

<div class="veh-gate">
    <table class="veh-gate__table">
        <thead>
            <tr>
                <th>Charge</th>
                <th>VAT code</th>
                <th style="text-align: right;">Amount</th>
                <th style="text-align: right;">VAT</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($charges as $charge)
                <tr>
                    <td>{{ $charge->description }}</td>
                    <td>{{ $charge->vatCode?->code ?? '—' }}</td>
                    <td style="text-align: right;">KES {{ $money($charge->amount) }}</td>
                    {{-- The rate is the one snapshotted at posting, not
                         whatever the code says today. --}}
                    <td style="text-align: right;">
                        KES {{ $money($charge->vat_amount) }}
                        <span class="veh-hero__note">@ {{ rtrim(rtrim(number_format((float) $charge->vat_rate, 3), '0'), '.') }}%</span>
                    </td>
                    <td>{{ $charge->remarks ?? '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2"><strong>Total</strong></td>
                <td style="text-align: right;"><strong>KES {{ $money($charges->sum('amount')) }}</strong></td>
                <td style="text-align: right;"><strong>KES {{ $money($charges->sum('vat_amount')) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
