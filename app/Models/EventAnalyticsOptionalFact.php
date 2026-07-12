<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventAnalyticsFactStatus;
use App\Enums\EventAnalyticsMetric;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

final class EventAnalyticsOptionalFact extends Model
{
    use HasTenantScope;

    protected $table = 'event_analytics_optional_facts';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'deduplication_hash',
        'request_hash',
        'subject_hash',
        'pseudonym_key_version',
        'consent_record_id',
    ];

    protected $casts = [
        'metric' => EventAnalyticsMetric::class,
        'status' => EventAnalyticsFactStatus::class,
        'dimensions' => 'array',
        'is_late' => 'boolean',
        'occurred_at' => 'immutable_datetime',
        'received_at' => 'immutable_datetime',
        'retention_due_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
    ];

    public $timestamps = false;
}
