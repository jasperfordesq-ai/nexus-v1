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
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketplacePayment extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_payments';

    protected $fillable = [
        'order_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'amount',
        'currency',
        'platform_fee',
        'seller_payout',
        'payment_method',
        'status',
        'refund_amount',
        'refund_reason',
        'refunded_at',
        'payout_status',
        'payout_id',
        'paid_out_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'seller_payout' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'paid_out_at' => 'datetime',
        'order_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function escrow(): HasOne
    {
        return $this->hasOne(MarketplaceEscrow::class, 'payment_id');
    }
}
