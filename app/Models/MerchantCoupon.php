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
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantCoupon extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'merchant_coupons';

    protected $fillable = [
        'tenant_id',
        'seller_id',
        'code',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'min_order_cents',
        'max_uses',
        'max_uses_per_member',
        'valid_from',
        'valid_until',
        'status',
        'applies_to',
        'applies_to_ids',
        'usage_count',
    ];

    protected $casts = [
        'applies_to_ids' => 'array',
        'discount_value' => 'decimal:2',
        'min_order_cents' => 'integer',
        'max_uses' => 'integer',
        'max_uses_per_member' => 'integer',
        'usage_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSellerProfile::class, 'seller_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(MerchantCouponRedemption::class, 'coupon_id');
    }
}
