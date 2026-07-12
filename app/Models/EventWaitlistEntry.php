<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventWaitlistQueueState;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Canonical timed waitlist entry for one user and capacity pool. */
final class EventWaitlistEntry extends Model
{
    use HasTenantScope;

    protected $table = 'event_waitlist_entries';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'capacity_pool_key',
        'allocation_key',
        'queue_state',
        'queue_version',
        'queue_sequence',
        'state_changed_at',
        'state_changed_by',
        'offered_at',
        'offer_expires_at',
        'offer_token_hash',
        'offer_token_used_at',
        'accepted_at',
        'accepted_registration_id',
        'expired_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'tenant_id',
        'offer_token_hash',
    ];

    protected $casts = [
        'queue_state' => EventWaitlistQueueState::class,
        'queue_version' => 'integer',
        'queue_sequence' => 'integer',
        'state_changed_at' => 'datetime',
        'offered_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'offer_token_used_at' => 'datetime',
        'accepted_at' => 'datetime',
        'accepted_registration_id' => 'integer',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function acceptedRegistration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'accepted_registration_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventWaitlistEntryHistory::class, 'waitlist_entry_id');
    }
}
