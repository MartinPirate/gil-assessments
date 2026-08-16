@php
    /** @var \App\Models\Driver $driver */
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Specimen licence — {{ $driver->name }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        .card { border: 1pt solid #111; padding: 8mm; }
        .banner { background: #111; color: #fff; padding: 2mm 3mm; font-size: 9pt; letter-spacing: 1pt; }
        h1 { font-size: 14pt; margin: 6mm 0 1mm; }
        dt { font-size: 8pt; color: #555; text-transform: uppercase; letter-spacing: 0.5pt; margin-top: 3mm; }
        dd { margin: 0.5mm 0 0; font-size: 11pt; }
        .warn { margin-top: 6mm; font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    <div class="card">
        <div class="banner">SPECIMEN — SEEDED DEMONSTRATION DATA — NOT A REAL LICENCE</div>

        <h1>{{ $driver->name }}</h1>

        <dl>
            <dt>Driver ID</dt>
            <dd>{{ $driver->national_id }}</dd>

            <dt>Telephone</dt>
            <dd>{{ $driver->phone }}</dd>

            <dt>On file since</dt>
            <dd>{{ now()->format('d/m/Y') }}</dd>
        </dl>

        <p class="warn">
            This placeholder stands in for a scan of the driver's licence so the
            gate screens can be demonstrated with a document present. It carries
            no authority and confers nothing.
        </p>
    </div>
</body>
</html>
