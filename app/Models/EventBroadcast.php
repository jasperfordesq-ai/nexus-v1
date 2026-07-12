<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventBroadcastStatus;
use App\Enums\EventBroadcastVariant;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventBroadcast extends Model
{
    use HasTenantScope;

    protected $table = 'event_broadcasts';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'created_by_user_id',
        'updated_by_user_id',
        'scheduled_by_user_id',
        'cancelled_by_user_id',
        'content_hash',
    ];

    protected $casts = [
        'variant' => EventBroadcastVariant::class,
        'status' => EventBroadcastStatus::class,
        'broadcast_version' => 'integer',
        'audience_segments' => 'array',
        'channels' => 'array',
        'recipient_count' => 'integer',
        'delivery_count' => 'integer',
        'delivered_count' => 'integer',
        'suppressed_count' => 'integer',
        'dead_letter_count' => 'integer',
        'scheduled_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
        'sent_at' => 'immutable_datetime',
        'failed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_broadcast_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventBroadcastHistory::class, 'broadcast_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(EventBroadcastDelivery::class, 'broadcast_id');
    }
}
