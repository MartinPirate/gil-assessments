@php
    $role = $getRecord()->role();

    // Same list the Roles and permissions screen builds from, so the two
    // cannot disagree about what a role may do.
    $capabilities = [
        'Raise and view invoices' => $role->canSell(),
        'Decide approvals' => $role->canApprove(),
        'Record vehicle movements' => $role->canOperateGate(),
        'Plan and assign trips' => $role->canManageTrips(),
        'See payments' => $role->canViewPayments(),
        'Read the audit trail' => $role->canViewAuditLog(),
        'Edit master data' => $role->canAdminister(),
    ];
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
