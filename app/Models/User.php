<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasTenantScope;

    protected $table = 'users';

    protected $fillable = [
        'tenant_id', 'name', 'first_name', 'last_name', 'email', 'password_hash',
        'role', 'status', 'avatar_url', 'bio', 'location', 'latitude', 'longitude',
        'phone', 'balance', 'is_verified', 'is_admin', 'is_super_admin', 'is_god',
        'is_tenant_super_admin', 'is_approved', 'onboarding_completed',
        'profile_type', 'organization_name', 'totp_enabled',
        'email_verified_at', 'last_active_at',
    ];

    protected $hidden = [
        'password_hash', 'totp_secret', 'totp_backup_codes',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'balance' => 'decimal:2',
        'is_verified' => 'boolean',
        'is_admin' => 'boolean',
        'is_super_admin' => 'boolean',
        'is_god' => 'boolean',
        'is_tenant_super_admin' => 'boolean',
        'is_approved' => 'boolean',
        'onboarding_completed' => 'boolean',
        'totp_enabled' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
                     ->withPivot('role', 'status')
                     ->withTimestamps();
    }

    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class);
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'receiver_id');
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function sentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'sender_id');
    }

    public function receivedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'receiver_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', ['admin', 'super_admin', 'tenant_admin']);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }
}
