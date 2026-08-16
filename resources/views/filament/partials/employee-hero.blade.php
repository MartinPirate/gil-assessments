@php
    /** @var \App\Models\SalesEmployee $employee */
    $employee = $getRecord();
    $stats = $this->stats();
@endphp

<div class="veh-hero">
    <div class="veh-hero__head">
        <span class="veh-hero__plate">{{ $employee->name }}</span>
        <span class="usr-badge">{{ $employee->code }}</span>
        @if ($employee->position)
            <span class="usr-badge">{{ $employee->position }}</span>
        @endif
    </div>

    <dl class="veh-hero__stats">
        <div><dt>Sold</dt><dd>KES {{ number_format((float) $stats['sold'], 2) }}</dd></div>
        <div><dt>Outstanding</dt><dd>KES {{ number_format((float) $stats['outstanding'], 2) }}</dd></div>
        <div><dt>Documents</dt><dd>{{ $stats['documents'] }}</dd></div>
        <div><dt>Drafts</dt><dd>{{ $stats['drafts'] }}</dd></div>
        <div><dt>Awaiting approval</dt><dd>{{ $stats['awaitingApproval'] }}</dd></div>
        <div><dt>Customers</dt><dd>{{ $stats['customers'] }}</dd></div>
        <div>
            <dt>Last raised</dt>
            <dd>{{ $stats['lastRaised']?->format('d/m/Y') ?? 'never' }}</dd>
        </div>
    </dl>

    <p class="veh-hero__note">Drafts are excluded from every figure above — a draft is not a sale.</p>
</div>
