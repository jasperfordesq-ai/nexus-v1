<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTemplateStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventTemplate extends Model
{
    use HasTenantScope;

    protected $table = 'event_templates';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'created_by_user_id', 'archived_by_user_id'];

    protected $casts = [
        'current_version' => 'integer',
        'status' => EventTemplateStatus::class,
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_template_delete_forbidden');
        });
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'source_event_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EventTemplateVersion::class, 'template_id');
    }

    public function materializations(): HasMany
    {
        return $this->hasMany(EventTemplateMaterialization::class, 'template_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(EventTemplateAudit::class, 'template_id');
    }
}
