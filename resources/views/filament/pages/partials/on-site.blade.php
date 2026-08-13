{{-- Live list of vehicles currently inside the gate. --}}
<div class="gate-onsite">
    <h3 class="gate-onsite__title">Currently On Site ({{ $logs->count() }})</h3>

    @if ($logs->isEmpty())
        <p class="gate-onsite__empty">No vehicles are currently gated in.</p>
    @else
        <ul class="gate-onsite__list">
            @foreach ($logs as $log)
                <li class="gate-onsite__item">
                    <div>
                        <span class="gate-onsite__plate">{{ $log->vehicle_number }}</span>
                        <span class="gate-onsite__driver">{{ $log->driver_name }}</span>
                    </div>
                    <div class="gate-onsite__meta">
                        <span class="gate-status-badge gate-status-badge--in">IN</span>
                        <span>{{ $log->time_in->format('d/m H:i') }}</span>
                        <span>{{ $log->duration }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
