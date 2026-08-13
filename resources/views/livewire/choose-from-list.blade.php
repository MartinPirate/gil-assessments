<div>
    <x-filament::modal
        id="choose-from-list"
        width="4xl"
        :close-by-clicking-away="true"
        display-classes="block"
    >
        <x-slot name="heading">
            {{ $this->getHeading() }}
        </x-slot>

        <div class="cfl-table">
            {{ $this->table }}
        </div>
    </x-filament::modal>
</div>
