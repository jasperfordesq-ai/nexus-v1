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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceOrder extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_orders';

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'marketplace_listing_id',
        'marketplace_offer_id',
        'checkout_key',
        'checkout_fingerprint',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'time_credits_used',
        'status',
        'stripe_checkout_mode',
        'payment_intent_id',
        'checkout_session_id',
        'escrow_released_at',
        'shipping_method',
        'shipping_option_id',
        'shipping_cost',
        'tracking_number',
        'tracking_url',
        'delivery_address',
        'delivery_notes',
        'buyer_confirmed_at',
        'seller_confirmed_at',
        'auto_complete_at',
        'cancelled_at',
        'cancellation_reason',
        'payment_expires_at',
        'wallet_transaction_id',
        'wallet_refund_transaction_id',
        'loyalty_redemption_id',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'delivery_address' => 'array',
        'time_credits_used' => 'float',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'escrow_released_at' => 'datetime',
        'buyer_confirmed_at' => 'datetime',
        'seller_confirmed_at' => 'datetime',
        'auto_complete_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'quantity' => 'integer',
        'buyer_id' => 'integer',
        'seller_id' => 'integer',
        'marketplace_listing_id' => 'integer',
        'marketplace_offer_id' => 'integer',
        'shipping_option_id' => 'integer',
        'wallet_transaction_id' => 'integer',
        'wallet_refund_transaction_id' => 'integer',
        'loyalty_redemption_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOffer::class, 'marketplace_offer_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(MarketplaceSellerRating::class, 'order_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(MarketplaceDispute::class, 'order_id');
    }

    // ---------------------------------------------------------------
    //  Scopes
    // ---------------------------------------------------------------

    public function scopeForBuyer(Builder $query, int $userId): Builder
    {
        return $query->where('buyer_id', $userId);
    }

    public function scopeForSeller(Builder $query, int $userId): Builder
    {
        return $query->where('seller_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'refunded']);
    }
}
