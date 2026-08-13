<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} — GIL Business Suite</title>
    @vite(['resources/css/app.css'])
    <style>
        :root { --navy:#1f4e79; --ink:#1b2733; --muted:#5b6b7d; --line:#d6dee7; }
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef1f5;
             font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
        .card{max-width:34rem;width:calc(100% - 2rem);background:#fff;border:1px solid var(--line);
              border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.08)}
        .card__bar{background:linear-gradient(180deg,var(--navy),#163a5a);color:#fff;
                   padding:.5rem .9rem;font-size:.82rem;font-weight:600}
        .card__body{padding:1.75rem}
        .code{font-size:3rem;font-weight:700;color:var(--navy);line-height:1;margin:0 0 .5rem}
        p{margin:0 0 1rem;color:var(--muted);line-height:1.6}
        .ref{display:inline-block;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
             font-size:.78rem;background:#f1f5f9;border:1px solid var(--line);border-radius:4px;padding:.3rem .55rem;color:#334155}
        .actions{margin-top:1.5rem;display:flex;gap:.5rem;flex-wrap:wrap}
        .btn{display:inline-block;padding:.45rem .9rem;border-radius:4px;font-size:.82rem;
             font-weight:600;text-decoration:none;border:1px solid var(--navy)}
        .btn--primary{background:var(--navy);color:#fff}
        .btn--ghost{color:var(--navy);background:#fff}
    </style>
</head>
<body>
    <main class="card">
        <div class="card__bar">GIL Business Suite</div>
        <div class="card__body">
            <p class="code">{{ $status }}</p>
            <p>{{ $message }}</p>

            @if ($status >= 500)
                {{-- Quotable by the user, greppable in the logs. --}}
                <p>Quote this reference if you contact support:<br>
                   <span class="ref">{{ $reference }}</span></p>
            @endif

            <div class="actions">
                <a class="btn btn--primary" href="{{ url('/admin') }}">Back to the app</a>
                <a class="btn btn--ghost" href="{{ url()->previous() }}">Go back</a>
            </div>
        </div>
    </main>
</body>
</html>
