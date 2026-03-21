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

class VolDonation extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_donations';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'opportunity_id',
        'giving_day_id',
        'amount',
        'currency',
        'payment_method',
        'payment_reference',
        'message',
        'is_anonymous',
        'status',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolOpportunity::class, 'opportunity_id');
    }

    public function givingDay(): BelongsTo
    {
        return $this->belongsTo(VolGivingDay::class, 'giving_day_id');
    }
}
