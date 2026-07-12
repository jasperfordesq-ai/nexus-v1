<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventBroadcastAction;
use App\Enums\EventBroadcastStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class EventBroadcastHistory extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_broadcast_history';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'actor_user_id',
        'idempotency_hash',
        'request_hash',
        'content_hash',
    ];

    protected $casts = [
        'broadcast_version' => 'integer',
        'action' => EventBroadcastAction::class,
        'from_status' => EventBroadcastStatus::class,
        'to_status' => EventBroadcastStatus::class,
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_broadcast_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_broadcast_history_immutable');
        });
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(EventBroadcast::class, 'broadcast_id');
    }
}
