<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Channel Partners can now be written to, so the module needs an `can_edit` grant.
 *
 * The migration that introduced the module (2026_08_02_000001_add_cp_module_permissions)
 * set can_edit false everywhere on the grounds that "CP is a directory, not a decision
 * surface — nothing on it writes". Bulk upload is the first thing that does, and without
 * this the Gate answers false for every role except the super admin, who bypasses it.
 *
 * Mirrors each role's `approvals` grant, same as the view permission did: whoever may
 * approve a broker's registration may create one from a vetted roster instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        $approvals = DB::table('role_permissions')->where('module', 'approvals')->get();

        foreach ($approvals as $permission) {
            DB::table('role_permissions')
                ->where('role_id', $permission->role_id)
                ->where('module', 'cp')
                ->update(['can_edit' => $permission->can_edit, 'updated_at' => now()]);
        }
    }

    /**
     * Back to the directory-only state the original migration described. Deleting the rows
     * instead would take the view grant with them, which this never touched.
     */
    public function down(): void
    {
        DB::table('role_permissions')->where('module', 'cp')
            ->update(['can_edit' => false, 'updated_at' => now()]);
    }
};
