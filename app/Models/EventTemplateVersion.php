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

/** Immutable configuration snapshot captured from one authorised source event. */
final class EventTemplateVersion extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_template_versions';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'captured_by_user_id',
        'capture_idempotency_hash',
        'capture_request_hash',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'schema_version' => 'integer',
        'payload' => 'array',
        'copied_fields' => 'array',
        'skipped_fields' => 'array',
        'source_lifecycle_version' => 'integer',
        'source_calendar_sequence' => 'integer',
        'source_updated_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_template_version_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_template_version_immutable');
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'template_id');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }
}
