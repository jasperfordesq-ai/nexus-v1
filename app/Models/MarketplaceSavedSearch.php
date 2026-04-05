<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceSavedSearch extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_saved_searches';

    protected $fillable = [
        'user_id',
        'name',
        'search_query',
        'filters',
        'alert_frequency',
        'alert_channel',
        'last_alerted_at',
        'is_active',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'last_alerted_at' => 'datetime',
    ];

    // ───────────────────────────────────────────────────────────
    //  Relationships
    // ───────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
