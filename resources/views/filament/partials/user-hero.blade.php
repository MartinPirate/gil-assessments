@php
    /** @var \App\Models\User $user */
    $user = $getRecord();
    $s = $this->getSummary();
    $role = $user->role();
    $initials = \Illuminate\Support\Str::of($user->name)->explode(' ')->take(2)
        ->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->implode('');
@endphp

<div class="usr-hero">
    <div class="usr-hero__identity">
        <span class="usr-hero__avatar" aria-hidden="true">{{ $initials }}</span>

        <div class="usr-hero__who">
            <span class="usr-hero__name">{{ $user->name }}</span>
            <a class="usr-hero__email" href="mailto:{{ $user->email }}">{{ $user->email }}</a>

            <div class="usr-hero__badges">
                <span class="usr-badge usr-badge--role">{{ $role->label() }}</span>

                @if ($user->is_active)
                    <span class="usr-badge usr-badge--active">Active</span>
                @else
                    <span class="usr-badge usr-badge--disabled">Deactivated</span>
                @endif

                @if ($s['isOnline'])
                    <span class="usr-badge usr-badge--online">Signed in now</span>
                @endif

                @if ($user->driver)
                    <span class="usr-badge usr-badge--linked">Driver: {{ $user->driver->name }}</span>
                @endif
            </div>
        </div>
    </div>

    <dl class="usr-hero__stats">
        <div class="usr-stat">
            <dt>Invoices raised</dt>
            <dd>{{ number_format($s['invoices']) }}</dd>
            <span class="usr-stat__sub">KES {{ number_format($s['invoiceValue'], 2) }}</span>
        </div>

        <div class="usr-stat">
            <dt>Approvals decided</dt>
            <dd>{{ number_format($s['decisions']) }}</dd>
            <span class="usr-stat__sub">
                @if ($role->canApprove())
                    Limit KES {{ number_format((float) ($user->approval_limit ?? 0), 2) }}
                @else
                    Not an approver
                @endif
            </span>
        </div>

        <div class="usr-stat">
            <dt>Gate movements</dt>
            <dd>{{ number_format($s['gateMovements']) }}</dd>
            <span class="usr-stat__sub">Vehicles in or out</span>
        </div>

        <div class="usr-stat">
            <dt>Sign-ins</dt>
            <dd>{{ number_format($s['signIns']) }}</dd>
            <span class="usr-stat__sub">
                {{ $s['lastSeen'] ? 'Last '.\Illuminate\Support\Carbon::parse($s['lastSeen'])->diffForHumans() : 'Never signed in' }}
            </span>
        </div>
    </dl>
</div>
