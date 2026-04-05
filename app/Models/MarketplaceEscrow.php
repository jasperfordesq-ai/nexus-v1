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

class MarketplaceEscrow extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_escrow';

    protected $fillable = [
        'order_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'held_at',
        'release_after',
        'released_at',
        'release_trigger',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'held_at' => 'datetime',
        'release_after' => 'datetime',
        'released_at' => 'datetime',
        'order_id' => 'integer',
        'payment_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayment::class, 'payment_id');
    }
}
