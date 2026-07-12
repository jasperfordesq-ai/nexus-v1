<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventOfflineSyncBatchStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Durable, idempotent envelope for one bounded offline scan upload. */
final class EventOfflineSyncBatch extends Model
{
    use HasTenantScope;

    protected $table = 'event_offline_sync_batches';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'payload_hash',
        'claim_token_hash',
        'submitted_by_user_id',
        'terminal_by_user_id',
    ];

    protected $casts = [
        'manifest_version' => 'integer',
        'item_count' => 'integer',
        'status' => EventOfflineSyncBatchStatus::class,
        'claim_attempts' => 'integer',
        'accepted_count' => 'integer',
        'conflict_count' => 'integer',
        'rejected_count' => 'integer',
        'available_at' => 'immutable_datetime',
        'claimed_at' => 'immutable_datetime',
        'claim_expires_at' => 'immutable_datetime',
        'last_claimed_at' => 'immutable_datetime',
        'last_released_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
        'dead_lettered_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_offline_batch_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(EventCheckinDevice::class, 'device_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventOfflineSyncItem::class, 'batch_id')
            ->orderBy('item_position');
    }
}
