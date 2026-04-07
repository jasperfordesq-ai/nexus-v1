<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation — represents a group conversation.
 *
 * 1-to-1 conversations do NOT use this table; they continue using
 * the sender_id/receiver_id pattern in the messages table.
 * Only group conversations (is_group=true) are stored here.
 */
class Conversation extends Model
{
    use HasTenantScope;

    protected $table = 'conversations';

    protected $fillable = [
        'is_group',
        'group_name',
        'group_avatar_url',
        'created_by',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class)->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
