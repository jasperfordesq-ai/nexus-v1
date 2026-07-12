<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only evidence for session registration and withdrawal mutations. */
final class EventSessionRegistrationHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_session_registration_history';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'event_registration_id',
        'event_registration_version',
        'actor_user_id',
        'request_hash',
    ];

    protected $casts = [
        'event_registration_version' => 'integer',
        'registration_version' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_session_registration_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_session_registration_history_immutable');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventSessionRegistration::class, 'registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
