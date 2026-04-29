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

class MerchantCouponRedemption extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'merchant_coupon_redemptions';

    protected $fillable = [
        'coupon_id',
        'tenant_id',
        'user_id',
        'order_id',
        'discount_applied_cents',
        'redeemed_at',
        'redemption_method',
        'qr_token',
        'qr_expires_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'discount_applied_cents' => 'integer',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(MerchantCoupon::class, 'coupon_id');
    }
}
