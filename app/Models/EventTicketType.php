<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTicketKind;
use App\Enums\EventTicketTypeStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventTicketType extends Model
{
    use HasTenantScope;

    protected $table = 'event_ticket_types';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'created_by',
        'updated_by',
        'activated_by',
        'paused_by',
        'archived_by',
    ];

    protected $casts = [
        'ticket_version' => 'integer',
        'kind' => EventTicketKind::class,
        'unit_price_credits' => 'decimal:2',
        'allocation_limit' => 'integer',
        'sales_opens_at_utc' => 'immutable_datetime',
        'sales_closes_at_utc' => 'immutable_datetime',
        'event_starts_at_utc_snapshot' => 'immutable_datetime',
        'per_member_limit' => 'integer',
        'eligibility_policy' => 'array',
        'refund_cutoff_at_utc' => 'immutable_datetime',
        'organizer_cancel_refundable' => 'boolean',
        'status' => EventTicketTypeStatus::class,
        'activated_at' => 'immutable_datetime',
        'paused_at' => 'immutable_datetime',
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_ticket_type_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(EventTicketEntitlement::class, 'ticket_type_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventTicketTypeHistory::class, 'ticket_type_id');
    }
}
