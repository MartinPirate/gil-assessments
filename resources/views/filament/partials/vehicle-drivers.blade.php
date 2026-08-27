@php
    /** @var Vehicle $vehicle */

    use App\Models\Vehicle;

    $vehicle = $getRecord();

    // Grouped in PHP rather than with a distinct query: the same driver appears
    // once per trip, and the interesting number is how many of those there were.
    //  this is a intential fuck up we left N+1 issue using early loading
//    $drivers = $vehicle->trips()
//    ->select('driver_id')
//    ->selectRaw('COUNT(*) as trips_count')
//    ->selectRaw('MAX(scheduled_at) as last_trip_at')
//    ->groupBy('driver_id')
//    ->with('driver:id,name') // Eager loads only the columns you need
//    ->orderByDesc('last_trip_at')
//    ->get()
//    ->map(fn ($row) => [
//        'driver' => $row->driver,
//        'name' => $row->driver?->name, // Avoids errors if a driver was deleted
//        'trips' => $row->trips_count,
//        'last' => $row->last_trip_at,
//    ]);
    $drivers = $vehicle->trips()
        ->with('driver')
        ->get()
        ->groupBy('driver_id')
        ->map(fn ($trips) => [
            'driver' => $trips->first()->driver,
            'name' => $trips->first()->driver_name,
            'trips' => $trips->count(),
            'last' => $trips->max('scheduled_at'),
        ])
        ->sortByDesc('last')
        ->values();
@endphp

@if ($drivers->isEmpty())
    <p class="veh-empty">Nobody has driven this vehicle yet.</p>
@else
    <ul class="veh-people">
        @foreach ($drivers as $entry)
            <li class="veh-person">
                <span class="veh-person__avatar" aria-hidden="true">
                    {{ \Illuminate\Support\Str::of($entry['name'])->explode(' ')->take(2)->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->implode('') }}
                </span>

                <span class="veh-person__body">
                    <span class="veh-person__name">{{ $entry['name'] }}</span>
                    <span class="veh-person__meta">
                        @if ($entry['driver']?->phone)
                            {{ $entry['driver']->phone }} &middot;
                        @endif
                        ID {{ $entry['driver']?->national_id ?? '—' }}
                    </span>
                </span>

                <span class="veh-person__count">
                    {{ $entry['trips'] }} {{ \Illuminate\Support\Str::plural('trip', $entry['trips']) }}
                    <span class="veh-person__last">{{ $entry['last']?->diffForHumans() }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
