@php
    use App\Enums\OrderStage;
    use App\Services\OrderLifecycleService;

    /** @var \App\Models\Invoice $record */
    $record = $getRecord();

    $lifecycle = app(OrderLifecycleService::class);
    $reached = $record->stageEvents->keyBy(fn ($event) => $event->stage->value);
    $position = $lifecycle->currentPosition($record);
    $cancelled = $reached->has(OrderStage::Cancelled->value);
@endphp

<div class="order-track" role="list" aria-label="Order progress">
    @foreach (OrderStage::track() as $stage)
        @php
            $event = $reached->get($stage->value);
            $isDone = $event !== null;
            // The step immediately after the furthest one reached is where the
            // order is waiting, provided nothing has cancelled it.
            $isNext = ! $isDone && ! $cancelled && $stage->position() === $position + 1;
        @endphp

        <div
            class="order-track__step
                @if ($isDone) order-track__step--done
                @elseif ($isNext) order-track__step--next
                @else order-track__step--todo @endif"
            role="listitem"
        >
            <div class="order-track__marker">
                @if ($isDone)
                    <x-filament::icon icon="heroicon-m-check" class="order-track__icon" />
                @else
                    <x-filament::icon :icon="$stage->icon()" class="order-track__icon" />
                @endif
            </div>

            <div class="order-track__body">
                <span class="order-track__label">{{ $stage->label() }}</span>

                @if ($isDone)
                    <time class="order-track__time" datetime="{{ $event->occurred_at->toIso8601String() }}">
                        {{ $event->occurred_at->format('d/m/Y H:i') }}
                    </time>
                @elseif ($isNext)
                    <span class="order-track__time">Awaiting</span>
                @else
                    <span class="order-track__time">&mdash;</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

@if ($cancelled)
    <p class="order-track__cancelled">
        <x-filament::icon icon="heroicon-m-x-circle" class="order-track__icon" />
        This order was cancelled on
        {{ $reached->get(OrderStage::Cancelled->value)->occurred_at->format('d/m/Y H:i') }}.
    </p>
@endif
