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

class MarketplaceReport extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_reports';

    protected $fillable = [
        'marketplace_listing_id',
        'reporter_id',
        'reason',
        'description',
        'evidence_urls',
        'status',
        'acknowledged_at',
        'resolved_at',
        'resolution_reason',
        'action_taken',
        'appeal_text',
        'appeal_resolved_at',
        'handled_by',
        'transparency_report_included',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'evidence_urls' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'appeal_resolved_at' => 'datetime',
        'transparency_report_included' => 'boolean',
        'handled_by' => 'integer',
        'marketplace_listing_id' => 'integer',
        'reporter_id' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
