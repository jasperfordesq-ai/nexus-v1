<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * CookieInventoryItem — represents a row in cookie_inventory.
 *
 * Not tenant-scoped with the global scope because cookies can be
 * global (tenant_id IS NULL) or tenant-specific. Scoping is handled
 * explicitly in the service.
 */
class CookieInventoryItem extends Model
{
    use HasFactory;

    protected $table = 'cookie_inventory';

    protected $fillable = [
        'cookie_name', 'category', 'purpose', 'duration',
        'third_party', 'tenant_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tenant_id' => 'integer',
    ];
}
