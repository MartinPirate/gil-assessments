<?php

use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles move out of a column on users and into Laratrust.
 *
 * The old design was a single `role` string with capability methods on an
 * enum. It read well, but it made approving a *job* rather than a *trust*:
 * the only way to let a manager approve was to make them an Approver, which
 * also meant they were not anything else.
 *
 * Approving is now a permission held by administrators and managers, and the
 * Approver role is retired — everyone who held it becomes a Manager, which is
 * the role that carries the same authority plus a name that describes a person.
 *
 * The column goes rather than being left in step with the pivot: two places
 * saying which role somebody holds is one place too many.
 */
return new class extends Migration
{
    /**
     * The retired role, and what its holders become.
     */
    private const RENAMED = ['approver' => 'manager'];

    public function up(): void
    {
        AccessControl::sync();

        $roleIds = Role::query()->pluck('id', 'name');

        $users = DB::table('users')->orderBy('id')->get(['id', 'role']);

        foreach ($users as $user) {
            $name = self::RENAMED[$user->role] ?? $user->role;
            $roleId = $roleIds[$name] ?? null;

            if ($roleId === null) {
                continue;
            }

            // updateOrInsert rather than insertOrIgnore: SQL Server has no
            // INSERT ... IGNORE, and this is idempotent either way.
            DB::table('role_user')->updateOrInsert([
                'role_id' => $roleId,
                'user_id' => $user->id,
                'user_type' => User::class,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('sales');
            $table->index('role');
        });

        // Managers go back as approvers: on the way down there is no role that
        // means "manager", and approving is what they were kept for.
        DB::statement("
            UPDATE u
            SET u.role = CASE WHEN r.name = 'manager' THEN 'approver' ELSE r.name END
            FROM users u
            INNER JOIN role_user ru ON ru.user_id = u.id AND ru.user_type = '".addslashes(User::class)."'
            INNER JOIN roles r ON r.id = ru.role_id
        ");
    }
};
