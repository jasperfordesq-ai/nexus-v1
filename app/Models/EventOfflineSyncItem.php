<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventOfflineSyncOperation;
use App\Enums\EventOfflineSyncOutcome;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Immutable submitted scan evidence; processing decisions are separate append-only rows. */
final class EventOfflineSyncItem extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_offline_sync_items';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'credential_hash_reference', 'submitted_payload_hash'];

    protected $casts = [
        'item_position' => 'integer',
        'operation' => EventOfflineSyncOperation::class,
        'observed_at' => 'immutable_datetime',
        'expected_attendance_version' => 'integer',
        'initial_outcome' => EventOfflineSyncOutcome::class,
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_offline_item_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_offline_item_immutable');
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(EventOfflineSyncBatch::class, 'batch_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(EventCheckinCredential::class, 'credential_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(EventOfflineSyncDecision::class, 'item_id')
            ->orderBy('decision_version');
    }
}
