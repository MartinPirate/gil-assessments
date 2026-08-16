{{--
    The route on OpenStreetMap tiles.

    Free: no API key, no billing account, no per-load quota. Click to drop the
    origin, click again for the destination, drag either pin to correct it —
    the coordinate fields below are written directly, so what you see is what
    gets saved.
--}}
@php
    $record = $getRecord();
    $statePath = $getStatePath();
    // Filament derives an input's DOM id from its state path.
    $id = fn (string $field) => 'data.'.$field;
@endphp

@once
    @vite('resources/js/route-map.js')
@endonce

<div
    x-data="sapRouteMap({
        origin: @js($record?->origin_latitude !== null ? [(float) $record->origin_latitude, (float) $record->origin_longitude] : null),
        destination: @js($record?->destination_latitude !== null ? [(float) $record->destination_latitude, (float) $record->destination_longitude] : null),
        fields: @js([
            'originLatitude' => $id('origin_latitude'),
            'originLongitude' => $id('origin_longitude'),
            'destinationLatitude' => $id('destination_latitude'),
            'destinationLongitude' => $id('destination_longitude'),
        ]),
    })"
    wire:ignore
    class="route-map"
>
    <div x-ref="canvas" class="route-map__canvas"></div>

    <p class="route-map__hint">
        Click once for the origin and again for the destination. Drag a pin to move it.
        Tiles &copy; OpenStreetMap contributors.
    </p>
</div>
