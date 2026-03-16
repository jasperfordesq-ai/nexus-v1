<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolApplication extends Model
{
    use HasTenantScope;

    protected $table = 'vol_applications';

    protected $fillable = [
        'tenant_id',
        'opportunity_id',
        'user_id',
        'message',
        'shift_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolOpportunity::class, 'opportunity_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(VolShift::class, 'shift_id');
    }
}
