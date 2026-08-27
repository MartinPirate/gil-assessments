@php
    /** @var Vehicle $vehicle */
    use App\Models\Vehicle;$vehicle = $getRecord();

    $routes = $vehicle->trips()
        ->with('route')
        ->get()
        ->groupBy('route_id')
        ->map(fn ($trips) => [
            'name' => $trips->first()->route_name,
            'route' => $trips->first()->route,
            'runs' => $trips->count(),
            'last' => $trips->max('scheduled_at'),
        ])
        ->sortByDesc('runs')
        ->values();

    $busiest = $routes->max('runs') ?: 1;
@endphp

@if ($routes->isEmpty())
    <p class="veh-empty">This vehicle has not run a route yet.</p>
@else
    <ul class="veh-routes">
        @foreach ($routes as $entry)
            <li class="veh-route">
                <span class="veh-route__head">
                    <span class="veh-route__name">{{ $entry['name'] }}</span>
                    <span class="veh-route__runs">{{ $entry['runs'] }}&times;</span>
                </span>
                <span class="veh-route__bar" aria-hidden="true">
                    <span class="veh-route__fill" style="width: {{ round(($entry['runs'] / $busiest) * 100) }}%"></span>
                </span>

                <span class="veh-route__meta">
                    @if ($entry['route']?->distance_km)
                        {{ number_format((float) $entry['route']->distance_km) }} km
                        &middot;
                    @endif
                    last run {{ $entry['last']?->diffForHumans() ?? '—' }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
