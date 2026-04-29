<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AG45 — A click-and-collect pickup slot offered by a seller.
 */
class MarketplacePickupSlot extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_pickup_slots';

    protected $fillable = [
        'tenant_id',
        'seller_id',
        'slot_start',
        'slot_end',
        'capacity',
        'booked_count',
        'is_recurring',
        'recurring_pattern',
        'is_active',
    ];

    protected $casts = [
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'capacity' => 'integer',
        'booked_count' => 'integer',
        'is_recurring' => 'boolean',
        'recurring_pattern' => 'array',
        'is_active' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSellerProfile::class, 'seller_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(MarketplacePickupReservation::class, 'slot_id');
    }
}
