<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAvailability extends Model
{
    use HasTenantScope;

    protected $table = 'member_availability';

    protected $fillable = [
        'tenant_id', 'user_id', 'day_of_week', 'start_time', 'end_time',
        'is_recurring', 'specific_date', 'note',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'day_of_week'  => 'integer',
        'is_recurring' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
