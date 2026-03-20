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

class ExchangeRequest extends Model
{
    use HasTenantScope;

    protected $table = 'exchange_requests';

    protected $fillable = [
        'tenant_id', 'listing_id', 'requester_id', 'provider_id',
        'proposed_hours', 'requester_notes', 'status',
        'broker_id', 'broker_notes',
        'requester_confirmed_at', 'requester_confirmed_hours',
        'provider_confirmed_at', 'provider_confirmed_hours',
        'final_hours', 'transaction_id',
    ];

    protected $casts = [
        'proposed_hours' => 'decimal:2',
        'requester_confirmed_hours' => 'decimal:2',
        'provider_confirmed_hours' => 'decimal:2',
        'final_hours' => 'decimal:2',
        'requester_confirmed_at' => 'datetime',
        'provider_confirmed_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broker_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ExchangeHistory::class, 'exchange_id')->orderBy('created_at');
    }
}
