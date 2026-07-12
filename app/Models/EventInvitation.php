<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventInvitationStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventInvitation extends Model
{
    use HasTenantScope;

    protected $table = 'event_invitations';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'email_ciphertext',
        'email_blind_hash',
        'token_hash',
        'token_fingerprint',
        'issue_idempotency_hash',
        'issue_request_hash',
    ];

    protected $casts = [
        'status' => EventInvitationStatus::class,
        'invitation_version' => 'integer',
        'token_expires_at' => 'immutable_datetime',
        'token_used_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'expired_at' => 'immutable_datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EventInvitationCampaign::class, 'campaign_id');
    }
}
