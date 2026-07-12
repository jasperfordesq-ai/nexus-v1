<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTicketKind;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class EventTicketEntitlementHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_ticket_entitlement_history';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'actor_user_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'entitlement_version' => 'integer',
        'units' => 'integer',
        'ticket_kind_snapshot' => EventTicketKind::class,
        'unit_price_credits_snapshot' => 'decimal:2',
        'total_price_credits_snapshot' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_ticket_entitlement_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_ticket_entitlement_history_immutable');
        });
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(EventTicketEntitlement::class, 'entitlement_id');
    }
}
