<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventOfflineSyncOutcome;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only disposition evidence produced after attendance processing or conflict review. */
final class EventOfflineSyncDecision extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_offline_sync_decisions';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'idempotency_key_hash', 'request_hash', 'decided_by_user_id'];

    protected $casts = [
        'decision_version' => 'integer',
        'outcome' => EventOfflineSyncOutcome::class,
        'attendance_version_before' => 'integer',
        'attendance_version_after' => 'integer',
        'attendance_activity_id' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_offline_decision_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_offline_decision_immutable');
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EventOfflineSyncItem::class, 'item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
