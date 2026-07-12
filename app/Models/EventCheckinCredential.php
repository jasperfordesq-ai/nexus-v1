<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventCheckinCredentialStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Revocable verifier for an opaque bearer credential issued to one registration. */
final class EventCheckinCredential extends Model
{
    use HasTenantScope;

    protected $table = 'event_checkin_credentials';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'token_hash',
        'issue_idempotency_hash',
        'issued_by_user_id',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'credential_version' => 'integer',
        'status' => EventCheckinCredentialStatus::class,
        'active_slot' => 'integer',
        'issued_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'rotated_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'expired_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_qr_credential_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
