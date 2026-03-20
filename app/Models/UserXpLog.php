<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserXpLog extends Model
{
    use HasTenantScope;

    protected $table = 'user_xp_log';

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'xp_amount', 'action', 'description',
    ];

    protected $casts = [
        'xp_amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
