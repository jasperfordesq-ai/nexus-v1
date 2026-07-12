<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSessionRegistrationStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Versioned self-service registration for one member and one session. */
final class EventSessionRegistration extends Model
{
    use HasTenantScope;

    protected $table = 'event_session_registrations';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'event_registration_id',
        'event_registration_version',
    ];

    protected $casts = [
        'event_registration_version' => 'integer',
        'version' => 'integer',
        'status' => EventSessionRegistrationStatus::class,
        'registered_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_session_registration_delete_forbidden');
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventSessionRegistrationHistory::class, 'registration_id')
            ->orderBy('registration_version');
    }
}
