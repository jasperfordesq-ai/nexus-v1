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

class MarketplaceDispute extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_disputes';

    protected $fillable = [
        'order_id',
        'opened_by',
        'reason',
        'description',
        'evidence_urls',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'refund_amount',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'evidence_urls' => 'array',
        'resolved_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'order_id' => 'integer',
        'opened_by' => 'integer',
        'resolved_by' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
