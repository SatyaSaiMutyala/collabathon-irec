<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * One table for all three actors, separated by `role`.
 * `email` is the login key for every role; `mobile` is profile data, not a credential.
 */
#[Fillable(['name', 'email', 'password', 'role', 'role_id', 'status', 'mobile', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_BROKER = 'broker';
    public const ROLE_DEVELOPER = 'developer';

    // A channel partner mid-registration: register/start (step 1) created the row, but
    // step 3's final submit hasn't happened yet. Deliberately distinct from PENDING —
    // ApprovalController's queue filters on PENDING specifically, so a half-finished
    // registration never shows up there.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAUSED = 'paused';
    // A broker or developer deleted their own account from the app — a soft delete via
    // this flag rather than Eloquent's SoftDeletes, so the row keeps showing up in the
    // admin's roster (visibly inactive) instead of vanishing from every default query.
    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ------------------------------------------------------------------ relations

    public function brokerProfile(): HasOne
    {
        return $this->hasOne(BrokerProfile::class);
    }

    public function developer(): HasOne
    {
        return $this->hasOne(Developer::class);
    }

    /** Leads this user raised as a broker. */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'broker_id');
    }

    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    /**
     * The admin-side staff role (Super Admin / Manager / custom) governing this
     * user's module permissions. Only meaningful when `role === 'admin'`; brokers
     * and developers never have one. Named `adminRole`, not `role`, so it never
     * collides with the `role` enum column/attribute.
     */
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ------------------------------------------------------------------ helpers

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** The one reserved role that bypasses every permission check. */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->adminRole?->is_system;
    }

    private function permissionFor(string $module): ?RolePermission
    {
        return $this->adminRole?->permissions->firstWhere('module', $module);
    }

    /** Fail-closed: no role, or no permission row for this module, means no access. */
    public function canView(string $module): bool
    {
        return $this->isSuperAdmin() || (bool) $this->permissionFor($module)?->can_view;
    }

    public function canEdit(string $module): bool
    {
        return $this->isSuperAdmin() || (bool) $this->permissionFor($module)?->can_edit;
    }

    public function canDelete(string $module): bool
    {
        return $this->isSuperAdmin() || (bool) $this->permissionFor($module)?->can_delete;
    }

    public function isBroker(): bool
    {
        return $this->role === self::ROLE_BROKER;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    /** Only an active account may hold a token or a session. */
    /**
     * `deleted_at` here means an admin moved this account to Trash (see Team/Channel
     * Partners/Approvals controllers) — a second, independent gate from `status`, which
     * still carries its own meaning (active/pending/inactive/etc.) untouched by a trash
     * action. Checking both here means every sign-in path is protected by one line
     * rather than each needing to remember to check `deleted_at` on top of `status`.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->deleted_at === null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Why the most recent decision on this account went against them — null if it
     * was never rejected, or if a later approval superseded that rejection (an old
     * rejection's row is kept for the audit trail, see ApprovalController::approve,
     * but the *latest* decision is what a broker resuming their draft should see).
     */
    public function latestRejectionReason(): ?string
    {
        $decision = $this->approvalDecisions()->latest()->first();

        return $decision?->decision === 'rejected' ? $decision->reason : null;
    }

    // ------------------------------------------------------------------ scopes

    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
