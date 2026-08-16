<?php

namespace App\Support;

use App\Enums\Permission as PermissionEnum;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;

/**
 * Provisions Laratrust's roles and permissions from the enums that describe
 * them.
 *
 * Both the migration that introduced the tables and the seeder that keeps a
 * fresh install current call this, so the matrix is written down once. Running
 * it repeatedly is safe and is the point: adding a permission to a role in
 * UserRole::permissions() and re-running brings the database into line.
 *
 * It only ever adds or re-points; it does not delete roles somebody created by
 * hand, because guessing that an unknown role is rubbish is how you revoke
 * somebody's access on a Friday afternoon.
 */
class AccessControl
{
    public static function sync(): void
    {
        $permissions = collect(PermissionEnum::cases())
            ->mapWithKeys(fn (PermissionEnum $permission) => [
                $permission->value => Permission::updateOrCreate(
                    ['name' => $permission->value],
                    ['display_name' => $permission->label()],
                ),
            ]);

        foreach (UserRole::cases() as $role) {
            $record = Role::updateOrCreate(
                ['name' => $role->value],
                ['display_name' => $role->label(), 'description' => $role->description()],
            );

            $record->permissions()->sync(
                collect($role->permissions())
                    ->map(fn (PermissionEnum $permission) => $permissions[$permission->value]->getKey())
                    ->all(),
            );
        }
    }
}
