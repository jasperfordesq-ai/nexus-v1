<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

class Message extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'messages';

    public $timestamps = false;

    protected $fillable = [
        'sender_id', 'receiver_id', 'listing_id',
        'body', 'is_read', 'is_edited', 'edited_at',
        'is_deleted_sender', 'is_deleted_receiver',
        'is_deleted', 'is_voice', 'audio_url', 'audio_duration',
        'transcript', 'transcript_language',
        'read_at', 'created_at',
        'context_type', 'context_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'is_deleted_sender' => 'boolean',
        'is_deleted_receiver' => 'boolean',
        'is_voice' => 'boolean',
        'audio_duration' => 'integer',
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withoutGlobalScopes();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id')->withoutGlobalScopes();
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeBetweenUsers(Builder $query, int $userId1, int $userId2): Builder
    {
        // Wrap both directions in a single where() so that other global scopes
        // (e.g., TenantScope) remain safely AND-ed outside this group.
        // Previously used ->where()->orWhere() which could break SQL precedence
        // when composed with other top-level conditions.
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where(function ($inner) use ($userId1, $userId2) {
                $inner->where('sender_id', $userId1)->where('receiver_id', $userId2);
            })->orWhere(function ($inner) use ($userId1, $userId2) {
                $inner->where('sender_id', $userId2)->where('receiver_id', $userId1);
            });
        });
    }

    /**
     * Delete all messages in a conversation between two users.
     */
    public static function deleteConversation(int $userId, int $otherUserId): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId, $otherUserId) {
                $q->where(function ($q2) use ($userId, $otherUserId) {
                    $q2->where('sender_id', $userId)->where('receiver_id', $otherUserId);
                })->orWhere(function ($q2) use ($userId, $otherUserId) {
                    $q2->where('sender_id', $otherUserId)->where('receiver_id', $userId);
                });
            })
            ->delete();

        return $affected > 0;
    }

    /**
     * Get reactions for multiple messages (batch).
     */
    public static function getReactionsBatch(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();

        // message_reactions has no tenant_id column — scope via JOIN to messages table
        $results = DB::table('message_reactions')
            ->join('messages', 'message_reactions.message_id', '=', 'messages.id')
            ->select([
                'message_reactions.message_id',
                'message_reactions.emoji',
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(message_reactions.user_id) as user_ids'),
            ])
            ->where('messages.tenant_id', $tenantId)
            ->whereIn('message_reactions.message_id', $messageIds)
            ->groupBy('message_reactions.message_id', 'message_reactions.emoji')
            ->orderBy('message_reactions.message_id')
            ->orderByRaw('MIN(message_reactions.created_at)')
            ->get();

        $grouped = [];
        foreach ($results as $row) {
            $msgId = $row->message_id;
            if (!isset($grouped[$msgId])) {
                $grouped[$msgId] = [];
            }
            $grouped[$msgId][] = [
                'emoji' => $row->emoji,
                'count' => (int) $row->count,
                'user_ids' => array_map('intval', explode(',', $row->user_ids)),
            ];
        }

        return $grouped;
    }

}
