<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokerMessageCopy extends Model
{
    use HasTenantScope;

    protected $table = 'broker_message_copies';

    protected $fillable = [
        'tenant_id', 'original_message_id', 'conversation_key',
        'sender_id', 'receiver_id', 'message_body', 'sent_at',
        'copy_reason', 'related_listing_id',
        'reviewed_by', 'reviewed_at', 'flagged',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'reviewed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function originalMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'original_message_id');
    }
}
