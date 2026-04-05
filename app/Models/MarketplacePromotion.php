<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePromotion extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_promotions';

    protected $fillable = [
        'marketplace_listing_id',
        'user_id',
        'promotion_type',
        'stripe_payment_intent_id',
        'amount_paid',
        'currency',
        'started_at',
        'expires_at',
        'is_active',
        'impressions',
        'clicks',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'is_active' => 'boolean',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ───────────────────────────────────────────────────────────
    //  Relationships
    // ───────────────────────────────────────────────────────────

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
