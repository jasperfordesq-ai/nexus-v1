<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSafetyCodeEvidenceAction;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only acknowledgement, withdrawal, and replacement evidence. */
final class EventSafetyCodeAcknowledgement extends Model
{
    use HasTenantScope;

    public const CREATED_AT = 'recorded_at';
    public const UPDATED_AT = null;

    protected $table = 'event_safety_code_acknowledgements';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'actor_user_id',
        'idempotency_hash',
        'request_hash',
    ];

    protected $casts = [
        'requirements_version_number' => 'integer',
        'evidence_sequence' => 'integer',
        'action' => EventSafetyCodeEvidenceAction::class,
        'acknowledged_at' => 'immutable_datetime',
        'recorded_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_safety_code_acknowledgement_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_safety_code_acknowledgement_immutable');
        });
    }
}
