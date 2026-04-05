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

class MarketplaceShippingOption extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_shipping_options';

    protected $fillable = [
        'seller_id',
        'courier_name',
        'courier_code',
        'price',
        'currency',
        'estimated_days',
        'is_default',
        'is_active',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'price' => 'decimal:2',
        'estimated_days' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'seller_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function seller(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSellerProfile::class, 'seller_id');
    }
}
