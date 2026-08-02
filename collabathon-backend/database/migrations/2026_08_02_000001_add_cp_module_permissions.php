<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Channel Partners is a new permission module, so every existing role needs a row for it.
 *
 * Without this the Gate answers false for everyone — including the super admin — and the
 * sidebar entry silently never appears. Access mirrors each role's existing `approvals`
 * grant, because CP is the same population of people at a later stage of the same
 * workflow: whoever may review a broker's registration may see them once approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        $approvals = DB::table('role_permissions')->where('module', 'approvals')->get();

        foreach ($approvals as $permission) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $permission->role_id, 'module' => 'cp'],
                [
                    'can_view' => $permission->can_view,
                    // CP is a directory, not a decision surface — nothing on it writes,
                    // so edit and delete are not granted even where approvals has them.
                    'can_edit' => false,
                    'can_delete' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        // A role with no approvals row at all still needs one for cp, or it would be
        // indistinguishable from "not yet migrated" the next time this is reasoned about.
        $covered = $approvals->pluck('role_id');

        DB::table('roles')->whereNotIn('id', $covered)->pluck('id')
            ->each(fn ($roleId) => DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'module' => 'cp'],
                [
                    'can_view' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ));
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('module', 'cp')->delete();
    }
};
