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

class VolLog extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'organization_id',
        'opportunity_id',
        'caring_support_relationship_id',
        'support_recipient_id',
        'date_logged',
        'hours',
        'description',
        'status',
        'assigned_to',
        'assigned_at',
        'escalated_at',
        'escalation_note',
    ];

    protected $casts = [
        'date_logged' => 'date',
        'hours' => 'decimal:2',
        'assigned_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolOpportunity::class, 'opportunity_id');
    }
}
