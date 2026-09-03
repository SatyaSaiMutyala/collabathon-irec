<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super-Admin-only: define custom admin-side roles (e.g. "Manager") and their
 * per-module view/edit/delete permissions. Gated at the route level (can:manage-team).
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles', [
            'roles' => Role::withCount('users')->with('permissions')->orderByDesc('is_system')->orderBy('name')->get(),
            'modules' => Role::MODULES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'is_system' => false]);
            $this->syncPermissions($role, $data['permissions']);
        });

        return back()->with('status', "Role \"{$data['name']}\" created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'The Super Admin role cannot be edited.');

        $data = $this->validatePayload($request, $role);

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            $this->syncPermissions($role, $data['permissions']);
        });

        return back()->with('status', "Role \"{$data['name']}\" updated.");
    }

    /** Moves the role to Trash — reversible, see restore(). Its permissions are untouched. */
    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'The Super Admin role cannot be deleted.');
        // A trashed team member doesn't count as "still assigned" — they no longer show
        // up anywhere in the admin panel, so a role only they held should not be stuck
        // undeletable because of them.
        abort_if($role->users()->whereNull('deleted_at')->exists(), 422, 'Reassign staff off this role before deleting it.');

        $role->delete();

        return back()->with('warning', "Role \"{$role->name}\" was moved to Trash.");
    }

    /** Undoes destroy() — the role and its permissions come back exactly as they were. */
    public function restore(int $role): RedirectResponse
    {
        $role = Role::onlyTrashed()->findOrFail($role);
        $role->restore();

        return redirect()->route('admin.trash')->with('success', "Role \"{$role->name}\" was restored.");
    }

    /** The irreversible version of destroy() — only reachable from Trash. */
    public function forceDelete(int $role): RedirectResponse
    {
        $role = Role::onlyTrashed()->findOrFail($role);
        $name = $role->name;
        $role->forceDelete();

        return redirect()->route('admin.trash')->with('warning', "Role \"{$name}\" was permanently deleted.");
    }

    /** @return array{name: string, permissions: array<string, array{view: bool, edit: bool, delete: bool}>} */
    private function validatePayload(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name' . ($role ? ",{$role->id}" : '')],
            'permissions' => ['required', 'array'],
        ]);

        $permissions = [];
        foreach (Role::MODULES as $module => $label) {
            $permissions[$module] = [
                'view' => $request->boolean("permissions.{$module}.view"),
                'edit' => $request->boolean("permissions.{$module}.edit"),
                'delete' => $request->boolean("permissions.{$module}.delete"),
            ];
        }

        return ['name' => $data['name'], 'permissions' => $permissions];
    }

    /** @param  array<string, array{view: bool, edit: bool, delete: bool}>  $permissions */
    private function syncPermissions(Role $role, array $permissions): void
    {
        foreach ($permissions as $module => $abilities) {
            RolePermission::updateOrCreate(
                ['role_id' => $role->id, 'module' => $module],
                ['can_view' => $abilities['view'], 'can_edit' => $abilities['edit'], 'can_delete' => $abilities['delete']]
            );
        }
    }
}
