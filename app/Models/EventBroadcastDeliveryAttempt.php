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

final class EventBroadcastDeliveryAttempt extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_broadcast_delivery_attempts';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'provider_evidence_id',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_broadcast_delivery_attempt_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_broadcast_delivery_attempt_immutable');
        });
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EventBroadcastDelivery::class, 'delivery_id');
    }
}
