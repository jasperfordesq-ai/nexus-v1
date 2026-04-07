<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MessageReaction — emoji reaction on a message.
 *
 * The message_reactions table stores individual user reactions.
 * When fetching messages, reactions are aggregated per emoji.
 */
class MessageReaction extends Model
{
    use HasTenantScope;

    protected $table = 'message_reactions';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    protected $casts = [
        'message_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
