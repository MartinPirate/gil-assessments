@php
    $logs = $getRecord()->gateLogs()->with(['vehicle', 'gatedInBy', 'gatedOutBy'])->limit(25)->get();
@endphp

@if ($logs->isEmpty())
    <p class="veh-empty">This driver has never passed the gate.</p>
@else
    <div class="veh-gate">
        <table class="veh-gate__table">
            <thead>
                <tr>
                    <th>Vehicle</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>On site</th>
                    <th>Recorded by</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->vehicle?->vehicle_number ?? '—' }}</td>
                        <td>{{ $log->time_in?->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->time_out?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $log->duration ?? '—' }}</td>
                        <td>{{ $log->gatedInBy?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
