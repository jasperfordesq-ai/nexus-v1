<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTemplateAuditAction;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only aggregate history; it contains internal codes and non-private metadata only. */
final class EventTemplateAudit extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_template_audit';

    protected $guarded = ['*'];

    protected $hidden = ['tenant_id', 'actor_user_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'template_version_number' => 'integer',
        'action' => EventTemplateAuditAction::class,
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_template_audit_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_template_audit_immutable');
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

    public function materializedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'materialized_event_id');
    }
}
