<x-filament-panels::page>
    <div class="roles-matrix">
        <table class="roles-matrix__table">
            <thead>
                <tr>
                    <th class="roles-matrix__role-head">Role</th>
                    @foreach ($this->getCapabilityLabels() as $capability)
                        <th class="roles-matrix__cap-head">{{ $capability }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->getMatrix() as $role)
                    <tr>
                        <td class="roles-matrix__role">
                            <span class="roles-matrix__role-name">{{ $role['label'] }}</span>
                            <span class="roles-matrix__holders">
                                {{ $role['holders'] }} {{ \Illuminate\Support\Str::plural('account', $role['holders']) }}
                            </span>
                        </td>

                        @foreach ($role['capabilities'] as $capability => $granted)
                            <td class="roles-matrix__cell">
                                @if ($granted)
                                    <x-filament::icon
                                        icon="heroicon-m-check-circle"
                                        class="roles-matrix__yes"
                                    />
                                    <span class="fi-sr-only">{{ $capability }}: yes</span>
                                @else
                                    <span class="roles-matrix__no" aria-hidden="true">&mdash;</span>
                                    <span class="fi-sr-only">{{ $capability }}: no</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="roles-matrix__note">
        Roles are defined in code, in <code>app/Enums/UserRole.php</code>, rather than stored as
        editable rows. That is what keeps them safe to rely on: a capability cannot be granted by
        accident, and every check against one can be found by searching for it. Assign a role to a
        person under <strong>Administration &rarr; Users</strong>.
    </p>
</x-filament-panels::page>
