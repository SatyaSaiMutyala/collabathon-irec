<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * One table for all three actors, separated by `role`.
 * `email` is the login key for every role; `mobile` is profile data, not a credential.
 */
#[Fillable(['name', 'email', 'password', 'role', 'status', 'mobile', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_BROKER = 'broker';
    public const ROLE_DEVELOPER = 'developer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAUSED = 'paused';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
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
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'broker_id');
    }

    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    // ------------------------------------------------------------------ helpers

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
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
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
