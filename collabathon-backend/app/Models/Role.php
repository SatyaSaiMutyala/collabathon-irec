<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An admin-side staff role (Super Admin, Manager, or any custom role). */
#[Fillable(['name', 'is_system'])]
class Role extends Model
{
    use SoftDeletes;

    /**
     * The 6 admin modules a role's permissions can be scoped to. Single source of
     * truth reused by nav gating, Gates, the Roles UI, and the seeder — the key
     * matches the `admin.blade.php` sidebar's `$navGroups` item `key` 1:1.
     */
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'approvals' => 'CP Approvals',
        'cp' => 'Channel Partners',
        'developers' => 'Developers',
        'master_data' => 'Master Data',
        'properties' => 'Listings',
        'leads' => 'CP Interest',
        'settings' => 'Settings',
        'trash' => 'Trash',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'deleted_at' => 'datetime',
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
