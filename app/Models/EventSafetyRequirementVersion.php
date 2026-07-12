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

/** Immutable safety configuration snapshot for one concrete event occurrence. */
final class EventSafetyRequirementVersion extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_safety_requirement_versions';

    protected $guarded = ['*'];

    protected $hidden = [
        'tenant_id',
        'captured_by_user_id',
        'idempotency_hash',
        'request_hash',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'minimum_age' => 'integer',
        'guardian_consent_required' => 'boolean',
        'minor_age_threshold' => 'integer',
        'code_of_conduct_required' => 'boolean',
        'eligibility_policy_metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_safety_requirement_version_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_safety_requirement_version_immutable');
        });
    }

    public function requirements(): BelongsTo
    {
        return $this->belongsTo(EventSafetyRequirement::class, 'requirements_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
