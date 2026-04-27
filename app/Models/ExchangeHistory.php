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

class ExchangeHistory extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'exchange_history';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'exchange_id', 'action', 'actor_id', 'actor_role',
        'old_status', 'new_status', 'notes', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function exchange(): BelongsTo
    {
        return $this->belongsTo(ExchangeRequest::class, 'exchange_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
