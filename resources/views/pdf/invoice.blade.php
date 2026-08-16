@php
    /** @var \App\Models\Invoice $invoice */
    $customer = $invoice->customer;
    $money = fn ($value) => number_format((float) $value, \App\Support\InvoiceCalculator::DOCUMENT_SCALE);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->document_number }}</title>
    <style>
        /* Deliberately plain: dompdf supports a small slice of CSS, and a
           document that has to be readable in ten years is the wrong place to
           lean on a layout engine. */
        @page { margin: 24mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 2mm; }
        .muted { color: #555; }
        .row { width: 100%; }
        .col { display: inline-block; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        th, td { padding: 2mm 2mm; text-align: left; }
        thead th { border-bottom: 0.6pt solid #111; font-size: 9pt; text-transform: uppercase; letter-spacing: 0.4pt; }
        tbody td { border-bottom: 0.3pt solid #ccc; }
        .num { text-align: right; }
        .totals { margin-top: 6mm; width: 62mm; float: right; }
        .totals td { padding: 1.4mm 0; border: 0; }
        .totals .grand td { border-top: 0.6pt solid #111; font-weight: bold; padding-top: 2.4mm; }
        .status { font-size: 9pt; border: 0.6pt solid #111; padding: 1mm 2mm; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #666; }
    </style>
</head>
<body>
    <div class="row">
        <div class="col" style="width: 60%;">
            <h1>{{ config('app.name') }}</h1>
            <div class="muted">A/R Invoice</div>
        </div>
        <div class="col" style="width: 38%; text-align: right;">
            <div><strong>{{ $invoice->document_number }}</strong></div>
            <div class="muted">{{ optional($invoice->posting_date)->format('d/m/Y') }}</div>
            <div style="margin-top: 2mm;"><span class="status">{{ $invoice->status }}</span></div>
        </div>
    </div>

    <div class="row" style="margin-top: 8mm;">
        <div class="col" style="width: 60%;">
            <div class="muted">Billed to</div>
            <div><strong>{{ $invoice->customer_display_name ?: $invoice->customer_name }}</strong></div>
            <div>{{ $invoice->customer_code }}</div>
            @if ($customer?->address_line)
                <div>{{ $customer->address_line }}</div>
                <div>{{ collect([$customer->city, $customer->county, $customer->postal_code])->filter()->implode(', ') }}</div>
            @endif
            @if ($invoice->contact_person)
                <div class="muted" style="margin-top: 1mm;">Attn: {{ $invoice->contact_person }}</div>
            @endif
            @if ($invoice->kra_pin)
                <div class="muted">KRA PIN: {{ $invoice->kra_pin }}</div>
            @endif
        </div>
        <div class="col" style="width: 38%; text-align: right;">
            <div class="muted">Currency</div>
            <div>{{ $invoice->currency }}</div>
            @if ($invoice->sales_employee_name)
                <div class="muted" style="margin-top: 2mm;">Sales employee</div>
                <div>{{ $invoice->sales_employee_name }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 16%;">Item</th>
                <th>Description</th>
                <th class="num" style="width: 12%;">Qty</th>
                <th class="num" style="width: 16%;">Price</th>
                <th class="num" style="width: 18%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->line_num }}</td>
                    <td>{{ $line->item?->item_no ?? '—' }}</td>
                    <td>{{ $line->item_description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                    <td class="num">{{ $money($line->price_after_discount) }}</td>
                    <td class="num">{{ $money($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Total before discount</td>
            <td class="num">{{ $money($invoice->total_before_discount) }}</td>
        </tr>
        @if ((float) $invoice->discount_percent > 0)
            <tr>
                <td>Discount ({{ rtrim(rtrim(number_format((float) $invoice->discount_percent, 3), '0'), '.') }}%)</td>
                <td class="num">{{ $money($invoice->total_after_discount - $invoice->total_before_discount) }}</td>
            </tr>
        @endif
        <tr>
            <td>Tax</td>
            <td class="num">{{ $money($invoice->tax_total) }}</td>
        </tr>
        @if ((float) $invoice->freight > 0)
            <tr>
                <td>Freight</td>
                <td class="num">{{ $money($invoice->freight) }}</td>
            </tr>
            {{-- Named charges, so the customer can see what the delivery
                 line is actually made of. --}}
            @foreach ($invoice->freightCharges as $charge)
                <tr>
                    <td style="padding-left: 4mm;" class="muted">{{ $charge->description }}</td>
                    <td class="num muted">{{ $money($charge->amount) }}</td>
                </tr>
            @endforeach
        @endif
        <tr class="grand">
            <td>Document total</td>
            <td class="num">{{ $invoice->currency }} {{ $money($invoice->document_total) }}</td>
        </tr>
        <tr>
            <td>Balance due</td>
            <td class="num">{{ $money($invoice->balance_due) }}</td>
        </tr>
    </table>

    <footer>
        {{ $invoice->document_number }} · generated {{ now()->format('d/m/Y H:i') }}
        @if ($invoice->remarks)
            · {{ \Illuminate\Support\Str::limit($invoice->remarks, 90) }}
        @endif
    </footer>
</body>
</html>
