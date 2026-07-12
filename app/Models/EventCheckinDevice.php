<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventCheckinDeviceStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Event-scoped staff device credential; only its one-way verifier is persisted. */
final class EventCheckinDevice extends Model
{
    use HasTenantScope;

    protected $table = 'event_checkin_devices';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'secret_hash',
        'registration_idempotency_hash',
        'last_rotation_idempotency_hash',
        'registered_by_user_id',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'device_version' => 'integer',
        'status' => EventCheckinDeviceStatus::class,
        'registered_at' => 'immutable_datetime',
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
            throw new LogicException('event_checkin_device_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(EventOfflineSyncBatch::class, 'device_id');
    }
}
