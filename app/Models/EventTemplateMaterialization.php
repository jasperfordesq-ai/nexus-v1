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

/** Immutable provenance linking a template version to exactly one fresh draft. */
final class EventTemplateMaterialization extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_template_materializations';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'materialized_by_user_id',
        'idempotency_hash',
        'request_hash',
    ];

    protected $casts = [
        'template_version_number' => 'integer',
        'schema_version' => 'integer',
        'schedule_start_utc' => 'immutable_datetime',
        'schedule_end_utc' => 'immutable_datetime',
        'schedule_all_day' => 'boolean',
        'override_fields' => 'array',
        'federation_normalized' => 'boolean',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_template_materialization_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_template_materialization_immutable');
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(EventTemplateVersion::class, 'template_version_id');
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    public function createdEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'created_event_id');
    }
}
