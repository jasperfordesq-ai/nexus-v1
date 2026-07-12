<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTicketEntitlementStatus;
use App\Enums\EventTicketKind;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class EventTicketEntitlement extends Model
{
    use HasTenantScope;

    protected $table = 'event_ticket_entitlements';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'allocation_idempotency_hash',
        'allocation_request_hash',
        'created_by',
        'cancelled_by',
    ];

    protected $casts = [
        'units' => 'integer',
        'ticket_kind_snapshot' => EventTicketKind::class,
        'unit_price_credits_snapshot' => 'decimal:2',
        'total_price_credits_snapshot' => 'decimal:2',
        'status' => EventTicketEntitlementStatus::class,
        'entitlement_version' => 'integer',
        'confirmed_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_ticket_entitlement_delete_forbidden');
        });
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'ticket_type_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
