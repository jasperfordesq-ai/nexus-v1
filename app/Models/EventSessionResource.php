<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSessionResourceType;
use App\Enums\EventSessionVisibility;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ordered, encrypted-at-rest resource for a single agenda session. */
final class EventSessionResource extends Model
{
    use HasTenantScope;

    protected $table = 'event_session_resources';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'url_ciphertext',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resource_type' => EventSessionResourceType::class,
        'visibility' => EventSessionVisibility::class,
        'position' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
