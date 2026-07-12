<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventCapacityRegistrationState;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Canonical capacity registration fact for one user and event pool. */
final class EventRegistration extends Model
{
    use HasTenantScope;

    protected $table = 'event_registrations';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'capacity_pool_key',
        'allocation_key',
        'registration_state',
        'registration_version',
        'state_changed_at',
        'state_changed_by',
        'invited_at',
        'pending_at',
        'confirmed_at',
        'declined_at',
        'cancelled_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'registration_state' => EventCapacityRegistrationState::class,
        'registration_version' => 'integer',
        'state_changed_at' => 'datetime',
        'invited_at' => 'datetime',
        'pending_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'declined_at' => 'datetime',
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

    public function history(): HasMany
    {
        return $this->hasMany(EventRegistrationHistory::class, 'registration_id');
    }
}
