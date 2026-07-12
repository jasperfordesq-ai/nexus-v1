<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventFederationAction;
use App\Enums\EventFederationDeliveryStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

final class EventFederationDelivery extends Model
{
    use HasTenantScope;

    protected $table = 'event_federation_deliveries';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'external_partner_id',
        'payload_schema_version',
        'event_aggregate_version',
        'event_calendar_version',
        'action',
        'idempotency_key',
        'payload_hash',
        'payload',
        'status',
        'available_at',
    ];

    protected $hidden = [
        'claim_token',
        'last_error',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'event_id' => 'integer',
        'external_partner_id' => 'integer',
        'payload_schema_version' => 'integer',
        'event_aggregate_version' => 'integer',
        'event_calendar_version' => 'integer',
        'action' => EventFederationAction::class,
        'payload' => 'array',
        'status' => EventFederationDeliveryStatus::class,
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'claimed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];
}
