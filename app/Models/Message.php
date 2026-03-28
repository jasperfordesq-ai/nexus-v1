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
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId1)->where('receiver_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('sender_id', $userId2)->where('receiver_id', $userId1);
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

        $results = DB::table('message_reactions')
            ->select([
                'message_id',
                'emoji',
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(user_id) as user_ids'),
            ])
            ->whereIn('message_id', $messageIds)
            ->groupBy('message_id', 'emoji')
            ->orderBy('message_id')
            ->orderByRaw('MIN(created_at)')
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

    /**
     * Send email notification for a new message.
     * Respects user's notification preferences.
     */
    public static function sendEmailNotification(int $recipientId, string $senderName, string $preview, int $senderId): void
    {
        try {
            // Get receiver's email notification preferences
            $pref = DB::table('notification_settings')
                ->where('user_id', $recipientId)
                ->where('context_type', 'global')
                ->where('context_id', 0)
                ->value('frequency');

            $frequency = $pref ?? 'instant';

            if ($frequency === 'off') {
                return;
            }

            $receiver = DB::table('users')
                ->where('id', $recipientId)
                ->select('email', 'first_name')
                ->first();

            if (!$receiver || empty($receiver->email)) {
                return;
            }

            $content = "{$senderName} sent you a message: {$preview}";

            $receiverName = $receiver->first_name ?? 'there';
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $baseUrl = TenantContext::getFrontendUrl();
            $slugPrefix = TenantContext::getSlugPrefix();
            $link = "{$slugPrefix}/messages/{$senderId}";

            $htmlBody = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">New Message</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">You have a new message from {$senderName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #64748b; margin: 0 0 8px;">Hi {$receiverName},</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #1e293b; margin: 0; font-style: italic;">"{$preview}"</p>
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$baseUrl}{$link}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                Read Message
            </a>
        </div>
    </div>
    <div style="text-align: center; padding: 16px; color: #94a3b8; font-size: 12px;">
        <p>You received this because you have a {$tenantName} account.</p>
        <p><a href="{$baseUrl}{$slugPrefix}/settings?tab=notifications" style="color: #6366f1;">Manage notification preferences</a></p>
    </div>
</div>
HTML;

            if ($frequency === 'instant') {
                $mailer = new \App\Core\Mailer();
                $mailer->send($receiver->email, "New Message from {$senderName}", $htmlBody);
            } else {
                // Queue for daily digest
                DB::table('notification_queue')->insert([
                    'user_id' => $recipientId,
                    'activity_type' => 'new_message',
                    'content_snippet' => substr($content, 0, 250),
                    'link' => $link,
                    'frequency' => $frequency,
                    'email_body' => $htmlBody,
                    'created_at' => now(),
                    'status' => 'pending',
                ]);
            }
        } catch (\Exception $e) {
            error_log('[Message] Email notification failed: ' . $e->getMessage());
        }
    }
}
