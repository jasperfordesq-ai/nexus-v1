<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    use HasTenantScope;

    protected $table = 'ai_usage';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'feature',
        'tokens_input',
        'tokens_output',
        'cost_usd',
    ];

    protected $casts = [
        'tokens_input' => 'integer',
        'tokens_output' => 'integer',
        'cost_usd' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
