<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventParticipationDecision;
use App\Enums\EventParticipationDenialReason;
use App\Enums\EventParticipationDenialStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EventParticipationDenial extends Model
{
    use HasTenantScope;

    protected $table = 'event_participation_denials';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'reviewed_by_user_id',
        'withdrawn_by_user_id',
        'expired_by_user_id',
        'create_idempotency_hash',
        'create_request_hash',
    ];

    protected $casts = [
        'decision_version' => 'integer',
        'decision' => EventParticipationDecision::class,
        'reason_code' => EventParticipationDenialReason::class,
        'status' => EventParticipationDenialStatus::class,
        'effective_from' => 'immutable_datetime',
        'effective_until' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'expired_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_participation_denial_service_write_required');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_participation_denial_delete_forbidden');
        });
    }
}
