<x-filament-panels::page class="gate-page">
    @php
        $trips = $this->getTrips();
        $logs = $this->getRecentGateLogs();
    @endphp

    <div class="trip-toolbar">
        <span class="trip-toolbar__count">{{ $trips->where('status', '!=', 'Completed')->where('status', '!=', 'Cancelled')->count() }} open</span>
        {{ $this->refreshAction }}
    </div>

    @if ($trips->isEmpty())
        <div class="gate-onsite">
            <p class="gate-onsite__empty">You have no trips assigned.</p>
        </div>
    @else
        <div class="trip-list">
            @foreach ($trips as $trip)
                {{-- wire:key is required: without it Livewire's DOM diffing can reassign
                     these repeated nodes and the click bindings stop firing. --}}
                <article wire:key="trip-{{ $trip->id }}" class="trip-card trip-card--{{ Str::slug($trip->status) }}">
                    <header class="trip-card__head">
                        <div>
                            <span class="trip-card__ref">{{ $trip->reference }}</span>
                            <span class="trip-card__route">{{ $trip->route?->path ?? $trip->route_name }}</span>
                        </div>
                        <span class="trip-badge trip-badge--{{ Str::slug($trip->status) }}">{{ $trip->status }}</span>
                    </header>

                    <dl class="trip-card__meta">
                        <div><dt>Vehicle</dt><dd>{{ $trip->vehicle_number }}</dd></div>
                        <div><dt>Scheduled</dt><dd>{{ $trip->scheduled_at?->format('d/m/Y H:i') }}</dd></div>
                        @if ($trip->departed_at)
                            <div><dt>Departed</dt><dd>{{ $trip->departed_at->format('d/m H:i') }}</dd></div>
                        @endif
                        @if ($trip->duration)
                            <div><dt>Elapsed</dt><dd>{{ $trip->duration }}</dd></div>
                        @endif
                    </dl>

                    @if ($trip->cargo_description)
                        <p class="trip-card__cargo">{{ $trip->cargo_description }}</p>
                    @endif

                    {{-- Only the action that is legal from the current status. --}}
                    <div class="trip-card__action">
                        @if ($trip->status === \App\Models\Trip::STATUS_SCHEDULED)
                            {{ ($this->startTripAction)(['trip' => $trip->id]) }}
                        @elseif ($trip->status === \App\Models\Trip::STATUS_IN_TRANSIT)
                            {{ ($this->completeTripAction)(['trip' => $trip->id]) }}
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($logs->isNotEmpty())
        <div class="gate-onsite">
            <h3 class="gate-onsite__title">My Recent Gate Movements</h3>
            <ul class="gate-onsite__list">
                @foreach ($logs as $log)
                    <li wire:key="gatelog-{{ $log->id }}" class="gate-onsite__item">
                        <div>
                            <span class="gate-onsite__plate">{{ $log->vehicle_number }}</span>
                            <span class="gate-onsite__driver">{{ $log->time_in->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="gate-onsite__meta">
                            <span class="gate-status-badge gate-status-badge--{{ strtolower($log->status) }}">{{ $log->status }}</span>
                            <span>{{ $log->duration }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    {{-- Required for inline actions: without it a confirmation modal has
         nowhere to render and the button appears to do nothing. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
