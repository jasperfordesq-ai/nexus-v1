<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSessionStatus;
use App\Enums\EventSessionType;
use App\Enums\EventSessionVisibility;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Canonical durable agenda session attached to one concrete event. */
final class EventSession extends Model
{
    use HasTenantScope;

    protected $table = 'event_sessions';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'room_key',
        'created_by',
        'updated_by',
        'cancelled_by',
        'capacity_registered',
        'viewer_registration_state',
        'viewer_registration_version',
        'viewer_can_register',
        'viewer_can_withdraw',
        'viewer_can_view_registered',
        'viewer_can_view_staff',
        'viewer_can_manage',
    ];

    protected $casts = [
        'version' => 'integer',
        'capacity' => 'integer',
        'session_type' => EventSessionType::class,
        'visibility' => EventSessionVisibility::class,
        'status' => EventSessionStatus::class,
        'starts_at_utc' => 'immutable_datetime',
        'ends_at_utc' => 'immutable_datetime',
        'position' => 'integer',
        'cancelled_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_session_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(EventSessionSpeaker::class, 'session_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(EventSessionResource::class, 'session_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventSessionRegistration::class, 'session_id')
            ->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventSessionHistory::class, 'session_id')
            ->orderBy('agenda_version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
