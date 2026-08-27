@php
    use App\Enums\Permission;$user = $getRecord();

    // Built from the permission catalogue and asked of the account itself, so
    // this screen cannot disagree about what the user may do.
    $capabilities = collect(Permission::cases())
        ->mapWithKeys(fn (Permission $permission) => [
            $permission->label() => $user->isAbleTo($permission->value),
        ])
        ->all();
@endphp

<ul class="usr-caps">
    @foreach ($capabilities as $label => $granted)
        <li class="usr-cap {{ $granted ? 'usr-cap--yes' : 'usr-cap--no' }}">
            <x-filament::icon
                :icon="$granted ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle'"
                class="usr-cap__icon"
            />
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ul>
