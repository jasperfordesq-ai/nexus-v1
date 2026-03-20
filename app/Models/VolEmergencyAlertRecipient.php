<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolEmergencyAlertRecipient extends Model
{
    protected $table = 'vol_emergency_alert_recipients';

    public $timestamps = false;

    protected $fillable = [
        'alert_id',
        'tenant_id',
        'user_id',
        'notified_at',
        'response',
        'responded_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(VolEmergencyAlert::class, 'alert_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
