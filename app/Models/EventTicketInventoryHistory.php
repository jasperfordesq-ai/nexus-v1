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

final class EventTicketInventoryHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_ticket_inventory_history';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'actor_user_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'entitlement_version' => 'integer',
        'quantity_delta' => 'integer',
        'confirmed_units_after' => 'integer',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_ticket_inventory_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_ticket_inventory_history_immutable');
        });
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(EventTicketEntitlement::class, 'entitlement_id');
    }
}
