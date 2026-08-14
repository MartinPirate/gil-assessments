@php
    /** @var \App\Models\Vehicle $vehicle */
    $logs = $getRecord()->gateLogs()->with(['gatedInBy', 'gatedOutBy'])->latest('time_in')->limit(25)->get();
@endphp

@if ($logs->isEmpty())
    <p class="veh-empty">This vehicle has never passed the gate.</p>
@else
    <div class="veh-gate">
        <table class="veh-gate__table">
            <thead>
                <tr>
                    <th>Driver</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>On site</th>
                    <th>Recorded by</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $log->driver_name }}</td>
                        <td>{{ $log->time_in?->format('d/m/Y H:i') }}</td>
                        <td>
                            {{ $log->time_out?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td>
                            @if ($log->time_out)
                                {{ $log->time_in->diffForHumans($log->time_out, true) }}
                            @else
                                <span class="veh-gate__now">Still inside</span>
                            @endif
                        </td>
                        <td>{{ $log->gatedInBy?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
