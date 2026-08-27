@php
    /** @var Vehicle $vehicle */
    use App\Models\Vehicle;

    $vehicle = $getRecord();
    $summary = $this->getSummary();
@endphp

<div class="veh-hero">
    <div class="veh-hero__art" aria-hidden="true">
        <svg viewBox="0 0 220 110" role="presentation" focusable="false">
            <ellipse cx="110" cy="98" rx="86" ry="7" class="veh-hero__shadow"/>

            {{-- Box body --}}
            <rect x="18" y="26" width="104" height="52" rx="5" class="veh-hero__body"/>
            <rect x="28" y="36" width="84" height="18" rx="3" class="veh-hero__panel"/>

            {{-- Cab --}}
            <path d="M122 42 h34 l24 24 v12 a4 4 0 0 1 -4 4 h-54 a4 4 0 0 1 -4 -4 v-32 a4 4 0 0 1 4 -4 z"
                  class="veh-hero__cab"/>
            <path d="M130 48 h22 l17 17 h-39 z" class="veh-hero__glass"/>

            {{-- Wheels --}}
            <circle cx="52" cy="84" r="12" class="veh-hero__tyre"/>
            <circle cx="52" cy="84" r="5" class="veh-hero__hub"/>
            <circle cx="152" cy="84" r="12" class="veh-hero__tyre"/>
            <circle cx="152" cy="84" r="5" class="veh-hero__hub"/>
        </svg>
    </div>

    <div class="veh-hero__id">
        <span class="veh-hero__plate">{{ $vehicle->vehicle_number }}</span>

        <div class="veh-hero__meta">
            <span>{{ $vehicle->make ?: 'Make not recorded' }}</span>
            <span class="veh-hero__dot">&middot;</span>
            <span>{{ $vehicle->vehicle_type ?: 'Type not recorded' }}</span>
        </div>

        <div class="veh-hero__badges">
            @if ($summary['onSite'])
                <span class="veh-badge veh-badge--in">
                    On site since {{ $summary['since']?->format('H:i') }}
                </span>
            @else
                <span class="veh-badge veh-badge--out">
                    @if ($summary['lastSeen'])
                        Off site &middot; last seen {{ $summary['lastSeen']->diffForHumans() }}
                    @else
                        Never gated in
                    @endif
                </span>
            @endif

            @unless ($vehicle->is_active)
                <span class="veh-badge veh-badge--retired">Retired</span>
            @endunless
        </div>
    </div>

    <dl class="veh-hero__stats">
        <div class="veh-stat">
            <dt>Trips</dt>
            <dd>{{ number_format($summary['trips']) }}</dd>
            <span class="veh-stat__sub">{{ number_format($summary['completed']) }} completed</span>
        </div>

        <div class="veh-stat">
            <dt>Distance</dt>
            <dd>{{ number_format($summary['distance']) }}<span class="veh-stat__unit">km</span></dd>
            <span class="veh-stat__sub">Completed trips only</span>
        </div>

        <div class="veh-stat">
            <dt>Orders carried</dt>
            <dd>{{ number_format($summary['orders']) }}</dd>
            <span class="veh-stat__sub">KES {{ number_format($summary['value'], 2) }}</span>
        </div>
    </dl>
</div>
