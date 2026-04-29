<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AG45 — Reservation linking a buyer's order to a pickup slot, with QR code.
 */
class MarketplacePickupReservation extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_pickup_reservations';

    protected $fillable = [
        'tenant_id',
        'slot_id',
        'listing_id',
        'order_id',
        'buyer_user_id',
        'qr_code',
        'status',
        'reserved_at',
        'picked_up_at',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'picked_up_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(MarketplacePickupSlot::class, 'slot_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
