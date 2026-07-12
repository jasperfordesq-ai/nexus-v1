<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventParticipationDecision;
use App\Enums\EventParticipationDenialAction;
use App\Enums\EventParticipationDenialReason;
use App\Enums\EventParticipationDenialStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EventParticipationDenialHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_participation_denial_history';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'reviewer_user_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'decision_version' => 'integer',
        'decision' => EventParticipationDecision::class,
        'reason_code' => EventParticipationDenialReason::class,
        'status' => EventParticipationDenialStatus::class,
        'action' => EventParticipationDenialAction::class,
        'effective_from' => 'immutable_datetime',
        'effective_until' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_participation_denial_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_participation_denial_history_immutable');
        });
    }
}
