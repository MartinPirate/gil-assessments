@php use App\Livewire\ChooseFromList; @endphp
<x-filament-panels::page class="sap-page">
    <form wire:submit="save" class="sap-form">
        {{-- The document window's title bar, including the chrome the SAP
             client shows. The buttons are decorative on the web: minimise and
             maximise have no meaning in a browser tab, so they are marked
             aria-hidden rather than pretending to be controls. --}}
        <div class="sap-titlebar">
            <span class="sap-titlebar__label">A/R Invoice</span>
            <span class="sap-titlebar__chrome" aria-hidden="true">
                <span class="sap-titlebar__btn sap-titlebar__btn--min"></span>
                <span class="sap-titlebar__btn sap-titlebar__btn--max"></span>
                <span class="sap-titlebar__btn sap-titlebar__btn--close"></span>
            </span>
        </div>

        {{ $this->form }}

        <div class="sap-actions">
            <div class="sap-actions__left">
                {{ $this->addAndNewAction }}
                {{ $this->addDraftAndNewAction }}
                {{ $this->cancelAction }}
            </div>

            <div class="sap-actions__right">
                {{ $this->copyFromAction }}
                {{ $this->copyToAction }}
            </div>
        </div>
    </form>

    {{-- Shared Choose From List modal for every CFL button on this page. --}}
    @livewire(ChooseFromList::class)

    <x-filament-actions::modals/>
</x-filament-panels::page>
