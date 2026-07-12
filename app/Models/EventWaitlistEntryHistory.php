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

/** Immutable evidence for one canonical waitlist transition. */
final class EventWaitlistEntryHistory extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_waitlist_entry_history';

    protected $guarded = [];

    protected $casts = [
        'queue_version' => 'integer',
        'queue_sequence' => 'integer',
        'from_state' => EventWaitlistQueueState::class,
        'to_state' => EventWaitlistQueueState::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(EventWaitlistEntry::class, 'waitlist_entry_id');
    }
}
