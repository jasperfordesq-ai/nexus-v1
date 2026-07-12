<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventGuardianConsentStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EventGuardianConsent extends Model
{
    use HasTenantScope;

    protected $table = 'event_guardian_consents';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'guardian_email_ciphertext',
        'guardian_identity_ciphertext',
        'guardian_email_blind_hash',
        'guardian_locale',
        'token_hash',
        'policy_binding_hash',
        'request_idempotency_hash',
        'request_hash',
        'requested_by_user_id',
        'withdrawn_by_user_id',
        'expired_by_user_id',
    ];

    protected $casts = [
        'requirements_version_number' => 'integer',
        'consent_version' => 'integer',
        'status' => EventGuardianConsentStatus::class,
        'requested_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'token_consumed_at' => 'immutable_datetime',
        'granted_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'expired_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_guardian_consent_service_write_required');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_guardian_consent_delete_forbidden');
        });
    }
}
