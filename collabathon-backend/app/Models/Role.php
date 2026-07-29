<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An admin-side staff role (Super Admin, Manager, or any custom role). */
#[Fillable(['name', 'is_system'])]
class Role extends Model
{
    /**
     * The 6 admin modules a role's permissions can be scoped to. Single source of
     * truth reused by nav gating, Gates, the Roles UI, and the seeder — the key
     * matches the `admin.blade.php` sidebar's `$navGroups` item `key` 1:1.
     */
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'approvals' => 'Broker Approvals',
        'developers' => 'Developers',
        'properties' => 'Properties',
        'leads' => 'Leads & Matches',
        'settings' => 'Settings',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
