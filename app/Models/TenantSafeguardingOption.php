<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-configured safeguarding option shown during onboarding.
 *
 * Each tenant defines their own options (via country presets or custom config).
 * These are NEVER exposed in public profile APIs.
 */
class TenantSafeguardingOption extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'tenant_safeguarding_options';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'tenant_id',
        'option_key',
        'option_type',
        'label',
        'description',
        'help_url',
        'sort_order',
        'is_active',
        'is_required',
        'select_options',
        'triggers',
        'preset_source',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'select_options' => 'array',
        'triggers' => 'array',
    ];

    public function preferences(): HasMany
    {
        return $this->hasMany(UserSafeguardingPreference::class, 'option_id');
    }

    /**
     * Scope to only active options, ordered by sort_order.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get the merged trigger defaults (false for any missing key).
     */
    public function getTrigger(string $key): bool
    {
        return (bool) ($this->triggers[$key] ?? false);
    }

    /**
     * Get the vetting type required by this option's triggers (if any).
     */
    public function getRequiredVettingType(): ?string
    {
        return $this->triggers['vetting_type_required'] ?? null;
    }
}
