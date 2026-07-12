<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

final class EventRegistrationRetentionRun extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_registration_retention_runs';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'candidate_hash',
        'idempotency_hash',
        'request_hash',
    ];

    protected $casts = [
        'as_of_utc' => 'immutable_datetime',
        'eligible_count' => 'integer',
        'affected_count' => 'integer',
        'completed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}
