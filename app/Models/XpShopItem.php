<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class XpShopItem extends Model
{
    use HasTenantScope;

    protected $table = 'xp_shop_items';

    protected $fillable = [
        'tenant_id', 'item_key', 'name', 'description', 'icon',
        'item_type', 'xp_cost', 'stock_limit', 'per_user_limit',
        'display_order', 'is_active',
    ];

    protected $casts = [
        'xp_cost' => 'integer',
        'stock_limit' => 'integer',
        'per_user_limit' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
