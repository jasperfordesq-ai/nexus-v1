<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Member safeguarding preference selection.
 *
 * Access-controlled: NEVER exposed in public profile API.
 * Only visible to the member themselves, tenant admins, and assigned brokers.
 * All reads are audit-logged.
 */
class UserSafeguardingPreference extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'user_safeguarding_preferences';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'option_id',
        'selected_value',
        'notes',
        'consent_given_at',
        'consent_ip',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'option_id' => 'integer',
        'consent_given_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(TenantSafeguardingOption::class, 'option_id');
    }

    /**
     * Scope to only active (non-revoked) preferences.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Whether this preference is currently active (not revoked).
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
