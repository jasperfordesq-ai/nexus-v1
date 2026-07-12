<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

final class EventInvitationCampaignHistory extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_invitation_campaign_history';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'revision' => 'integer',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
