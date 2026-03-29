<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class TenantBadgeOverride extends Model
{
    use HasTenantScope;

    protected $table = 'tenant_badge_overrides';

    protected $fillable = [
        'tenant_id',
        'badge_key',
        'is_enabled',
        'custom_threshold',
        'custom_name',
        'custom_description',
        'custom_icon',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'custom_threshold' => 'integer',
    ];
}
