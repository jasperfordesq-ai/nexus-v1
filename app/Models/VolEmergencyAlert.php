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
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolEmergencyAlert extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_emergency_alerts';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'shift_id',
        'created_by',
        'priority',
        'message',
        'required_skills',
        'status',
        'expires_at',
        'filled_at',
        'created_at',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'expires_at' => 'datetime',
        'filled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(VolShift::class, 'shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(VolEmergencyAlertRecipient::class, 'alert_id');
    }
}
