<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserXpPurchase extends Model
{
    use HasTenantScope;

    protected $table = 'user_xp_purchases';

    protected $fillable = [
        'tenant_id', 'user_id', 'item_id', 'xp_spent', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'xp_spent' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(XpShopItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
