@php use App\Livewire\ChooseFromList; @endphp
<x-filament-panels::page class="gate-page">
    <form wire:submit="save">
        {{ $this->form }}

        <div class="gate-actions">
            {{ $this->saveAction }}
        </div>
    </form>

    @include('filament.pages.partials.on-site', ['logs' => $this->getVehiclesOnSite()])

    @livewire(ChooseFromList::class)
</x-filament-panels::page>
