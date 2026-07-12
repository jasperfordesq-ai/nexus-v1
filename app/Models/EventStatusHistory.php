<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventOperationalState;
use App\Enums\EventPublicationState;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only lifecycle audit record. Database triggers enforce the same rule. */
final class EventStatusHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_status_history';

    protected $guarded = ['*'];

    protected $casts = [
        'from_publication_status' => EventPublicationState::class,
        'to_publication_status' => EventPublicationState::class,
        'from_operational_status' => EventOperationalState::class,
        'to_operational_status' => EventOperationalState::class,
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_status_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_status_history_immutable');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
