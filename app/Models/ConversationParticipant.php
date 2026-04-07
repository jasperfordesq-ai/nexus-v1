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
 * ConversationParticipant — participant in a group conversation.
 */
class ConversationParticipant extends Model
{
    use HasTenantScope;

    protected $table = 'conversation_participants';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'muted_until',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'user_id' => 'integer',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'muted_until' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
