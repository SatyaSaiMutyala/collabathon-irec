<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Defines the admin panel's per-module permission Gates. Super Admin bypasses every
 * check (Gate::before); everyone else is resolved through User::canView/canEdit/canDelete,
 * which look up their assigned Role's RolePermission row for that module.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::define('view-module', fn (User $user, string $module) => $user->canView($module));
        Gate::define('edit-module', fn (User $user, string $module) => $user->canEdit($module));
        Gate::define('delete-module', fn (User $user, string $module) => $user->canDelete($module));

        Gate::define('manage-team', fn (User $user) => $user->isSuperAdmin());
    }
}
