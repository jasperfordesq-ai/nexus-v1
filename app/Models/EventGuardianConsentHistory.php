<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventGuardianConsentAction;
use App\Enums\EventGuardianConsentStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EventGuardianConsentHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_guardian_consent_history';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'actor_user_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'consent_version' => 'integer',
        'status' => EventGuardianConsentStatus::class,
        'action' => EventGuardianConsentAction::class,
        'evidence' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_guardian_consent_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_guardian_consent_history_immutable');
        });
    }
}
