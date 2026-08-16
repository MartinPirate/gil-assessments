@php
    /** @var \App\Models\Driver $driver */
    $driver = $getRecord();
    $stats = $this->stats();
@endphp

<div class="veh-hero">
    <div class="veh-hero__head">
        <span class="veh-hero__plate">{{ $driver->name }}</span>
        <span class="usr-badge">{{ $driver->national_id }}</span>
        @if ($driver->is_active)
            <span class="usr-badge usr-badge--ok">Active</span>
        @else
            <span class="usr-badge usr-badge--off">Inactive</span>
        @endif
        @if ($driver->hasLicence())
            <span class="usr-badge usr-badge--ok">Licence on file</span>
        @else
            <span class="usr-badge usr-badge--warn">No licence on file</span>
        @endif
    </div>

    <dl class="veh-hero__stats">
        <div><dt>Phone</dt><dd>{{ $driver->phone }}</dd></div>
        <div><dt>Login</dt><dd>{{ $driver->user?->email ?? '—' }}</dd></div>
        <div><dt>Trips</dt><dd>{{ $stats['trips'] }}</dd></div>
        <div><dt>Completed</dt><dd>{{ $stats['completed'] }}</dd></div>
        <div><dt>In transit</dt><dd>{{ $stats['inTransit'] }}</dd></div>
        <div><dt>Distance driven</dt><dd>{{ number_format((float) $stats['distance']) }} km</dd></div>
        <div><dt>Gate movements</dt><dd>{{ $stats['gateMovements'] }}</dd></div>
        <div>
            <dt>Last seen</dt>
            <dd>{{ $stats['lastSeen']?->format('d/m/Y H:i') ?? 'never' }}</dd>
        </div>
    </dl>

    @if ($stats['onSite'])
        <p class="veh-hero__note">
            On site now in {{ $stats['onSite']->vehicle?->vehicle_number }},
            since {{ $stats['onSite']->time_in?->format('d/m/Y H:i') }}.
        </p>
    @endif
</div>
