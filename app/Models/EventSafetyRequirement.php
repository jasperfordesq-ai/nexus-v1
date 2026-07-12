<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventSafetyRequirementStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventSafetyRequirement extends Model
{
    use HasTenantScope;

    protected $table = 'event_safety_requirements';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'created_by_user_id',
        'updated_by_user_id',
        'published_by_user_id',
        'archived_by_user_id',
    ];

    protected $casts = [
        'revision' => 'integer',
        'current_version' => 'integer',
        'published_version' => 'integer',
        'status' => EventSafetyRequirementStatus::class,
        'published_at' => 'immutable_datetime',
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_safety_requirements_service_write_required');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_safety_requirements_delete_forbidden');
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EventSafetyRequirementVersion::class, 'requirements_id');
    }
}
