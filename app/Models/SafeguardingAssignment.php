<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeguardingAssignment extends Model
{
    use HasTenantScope;

    protected $table = 'safeguarding_assignments';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'guardian_user_id',
        'ward_user_id',
        'assigned_by',
        'assigned_at',
        'consent_given_at',
        'revoked_at',
        'notes',
    ];

    protected $casts = [
        'guardian_user_id' => 'integer',
        'ward_user_id' => 'integer',
        'assigned_by' => 'integer',
        'assigned_at' => 'datetime',
        'consent_given_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ward_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
