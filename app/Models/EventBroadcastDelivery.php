<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventBroadcastChannel;
use App\Enums\EventBroadcastDeliveryStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventBroadcastDelivery extends Model
{
    use HasTenantScope;

    protected $table = 'event_broadcast_deliveries';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'recipient_user_id',
        'delivery_key',
        'claim_token',
        'provider_evidence_id',
    ];

    protected $casts = [
        'frozen_broadcast_version' => 'integer',
        'channel' => EventBroadcastChannel::class,
        'status' => EventBroadcastDeliveryStatus::class,
        'attempts' => 'integer',
        'available_at' => 'immutable_datetime',
        'next_attempt_at' => 'immutable_datetime',
        'claimed_at' => 'immutable_datetime',
        'delivered_at' => 'immutable_datetime',
        'suppressed_at' => 'immutable_datetime',
        'dead_lettered_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_broadcast_delivery_delete_forbidden');
        });
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(EventBroadcast::class, 'broadcast_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function attemptsHistory(): HasMany
    {
        return $this->hasMany(EventBroadcastDeliveryAttempt::class, 'delivery_id');
    }
}
