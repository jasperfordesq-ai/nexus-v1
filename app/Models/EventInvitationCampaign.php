<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventInvitationCampaignType;
use App\Enums\EventInvitationCampaignStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventInvitationCampaign extends Model
{
    use HasTenantScope;

    protected $table = 'event_invitation_campaigns';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'source_hash',
        'source_snapshot_ciphertext',
        'idempotency_hash',
        'request_hash',
    ];

    protected $casts = [
        'campaign_type' => EventInvitationCampaignType::class,
        'status' => EventInvitationCampaignStatus::class,
        'revision' => 'integer',
        'preview_count' => 'integer',
        'valid_count' => 'integer',
        'error_count' => 'integer',
        'preview_errors' => 'array',
        'source_schema_version' => 'integer',
        'segment_criteria_summary' => 'array',
        'scheduled_for_utc' => 'immutable_datetime',
        'issued_at' => 'immutable_datetime',
        'started_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class, 'campaign_id');
    }
}
