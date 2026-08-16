@php
    $trips = $getRecord()->trips()->with(['route', 'vehicle'])->limit(25)->get();
@endphp

@if ($trips->isEmpty())
    <p class="veh-empty">This driver has not been assigned a trip yet.</p>
@else
    <div class="veh-gate">
        <table class="veh-gate__table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Route</th>
                    <th>Vehicle</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trips as $trip)
                    <tr>
                        <td>{{ $trip->reference }}</td>
                        <td>{{ $trip->route?->name ?? '—' }}</td>
                        <td>{{ $trip->vehicle?->vehicle_number ?? '—' }}</td>
                        <td>{{ $trip->scheduled_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $trip->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
