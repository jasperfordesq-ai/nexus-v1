<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Events\MessageSent;
use App\Events\SafeguardingContactAttemptBlocked;
use App\Events\SafeguardingCoordinationRequested;
use App\Models\Message;
use App\Models\User;
use App\Services\SafeguardingTriggerService;
use App\Services\VettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MessageService — Laravel DI-based service for messaging operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class MessageService
{
    public function __construct(
        private readonly Message $message,
    ) {}

    /**
     * Get user's conversations (inbox) with cursor-based pagination.
     *
     * Groups messages by conversation partner and returns the latest message
     * in each conversation, ordered by most recent.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getConversations(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        // Get the latest message ID per conversation partner
        $tenantId = app('tenant.id');
        $showArchived = (bool) ($filters['archived'] ?? false);

        $latestIds = DB::table('messages')
            ->selectRaw('
                MAX(id) as latest_id,
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id
            ', [$userId])
            ->where('tenant_id', $tenantId)
            ->where('is_federated', 0)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->when(!$showArchived, function ($q) use ($userId) {
                // Inbox: exclude messages archived by the current user
                $q->whereRaw('NOT (sender_id = ? AND archived_by_sender IS NOT NULL)', [$userId])
                  ->whereRaw('NOT (receiver_id = ? AND archived_by_receiver IS NOT NULL)', [$userId]);
            })
            ->when($showArchived, function ($q) use ($userId) {
                // Archive tab: only messages archived by the current user
                $q->where(function ($q2) use ($userId) {
                    $q2->whereRaw('(sender_id = ? AND archived_by_sender IS NOT NULL)', [$userId])
                       ->orWhereRaw('(receiver_id = ? AND archived_by_receiver IS NOT NULL)', [$userId]);
                });
            })
            ->groupByRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END', [$userId])
            ->orderByDesc('latest_id');

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $latestIds->having('latest_id', '<', (int) $cursorId);
            }
        }

        $conversationIds = $latestIds->limit($limit + 1)->pluck('latest_id');

        $hasMore = $conversationIds->count() > $limit;
        if ($hasMore) {
            $conversationIds->pop();
        }

        $messages = Message::query()
            ->with([
                'sender:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
            ])
            ->whereIn('id', $conversationIds)
            ->orderByDesc('id')
            ->get();

        // Batch-fetch unread counts per partner (avoids N+1)
        $partnerIds = $messages->map(function (Message $msg) use ($userId) {
            return $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;
        })->unique()->values()->all();

        $unreadCounts = [];
        if (!empty($partnerIds)) {
            $unreadQuery = DB::table('messages')
                ->selectRaw('sender_id, COUNT(*) as cnt')
                ->where('tenant_id', $tenantId)
                ->where('is_federated', 0)
                ->where('receiver_id', $userId)
                ->where('is_read', false);

            self::applyReceiverUnreadVisibilityFilters($unreadQuery);

            $rows = $unreadQuery->whereIn('sender_id', $partnerIds)
                ->groupBy('sender_id')
                ->get();
            foreach ($rows as $row) {
                $unreadCounts[(int) $row->sender_id] = (int) $row->cnt;
            }
        }

        $items = $messages->map(function (Message $msg) use ($userId, $unreadCounts) {
            $data = $msg->toArray();
            $partnerId = $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;
            $partner = $msg->sender_id === $userId ? $msg->receiver : $msg->sender;
            $data['partner_id'] = $partnerId;
            $data['unread_count'] = $unreadCounts[$partnerId] ?? 0;
            $data['other_user'] = $partner ? [
                'id'         => $partner->id,
                'name'       => $partner->name,
                'first_name' => $partner->first_name,
                'last_name'  => $partner->last_name,
                'avatar_url' => $partner->avatar_url,
                'is_online'  => ($partner->last_active_at && $partner->last_active_at->gt(now()->subMinutes(5))),
            ] : null;
            $data['last_message'] = [
                'id'         => $msg->id,
                'body'       => $msg->body,
                'content'    => $msg->body, // Deprecated alias — kept for backward compat
                'sender_id'  => $msg->sender_id,
                'created_at' => $msg->created_at?->toISOString(),
                'is_read'    => $msg->is_read,
            ];
            $data['id'] = $partnerId;
            return $data;
        })->all();

        return [
            'items'    => array_values($items),
            'cursor'   => $hasMore && $messages->isNotEmpty() ? base64_encode((string) $messages->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get messages in a conversation with a specific user, with cursor-based pagination.
     *
     * @param int $partnerId The other user's ID
     * @param int $userId The authenticated user's ID
     * @param array $filters Pagination filters (limit, cursor, direction)
     * @return array{items: array, cursor: string|null, has_more: bool}|null Null if partner user not found
     */
    public static function getMessages(int $partnerId, int $userId, array $filters = []): ?array
    {
        // Verify the partner user exists within the same tenant
        $partner = User::withoutGlobalScopes()
            ->where('id', $partnerId)
            ->where('tenant_id', app('tenant.id'))
            ->first();
        if ($partner === null) {
            return null;
        }

        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;
        $direction = $filters['direction'] ?? 'older';

        $query = Message::query()
            ->with([
                'sender:id,first_name,last_name,avatar_url',
                'receiver:id,first_name,last_name,avatar_url',
                'attachments:id,message_id,file_url,file_name,file_size,mime_type,created_at',
            ])
            ->betweenUsers($userId, $partnerId);

        if (self::hasPerUserDeleteColumns()) {
            $query->whereRaw('NOT (sender_id = ? AND is_deleted_sender = 1)', [$userId])
                  ->whereRaw('NOT (receiver_id = ? AND is_deleted_receiver = 1)', [$userId]);
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                if ($direction === 'newer') {
                    $query->where('id', '>', (int) $cursorId);
                } else {
                    $query->where('id', '<', (int) $cursorId);
                }
            }
        }

        if ($direction === 'newer') {
            $query->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages->pop();
        }

        // Note: markAsRead is called by the controller, not here, to avoid double-calling.

        $items = $messages->map(fn (Message $msg) => $msg->toArray())->all();

        return [
            'items'    => array_values($items),
            'cursor'   => $hasMore && $messages->isNotEmpty() ? base64_encode((string) $messages->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Send a message.
     *
     * Accepts either (senderId, receiverId, data) or (senderId, data) where
     * data contains 'recipient_id'. The second form is used by controllers
     * passing getAllInput() directly.
     *
     * @param int $senderId
     * @param int|array $receiverIdOrData
     * @param array|null $data
     * @return array The created message as an array
     */
    public static function send(int $senderId, int|array $receiverIdOrData, ?array $data = null): array
    {
        if (is_array($receiverIdOrData)) {
            // Controller-style call: send($userId, $allInput)
            $data = $receiverIdOrData;
            $receiverId = (int) ($data['recipient_id'] ?? 0);
        } else {
            // Direct call: send($senderId, $receiverId, $data)
            $receiverId = $receiverIdOrData;
            $data = $data ?? [];
        }

        if ($receiverId <= 0) {
            self::$errors = [['code' => 'VALIDATION_ERROR', 'message' => __('api.message_recipient_required')]];
            return [];
        }

        if ($senderId === $receiverId) {
            self::$errors = [['code' => 'VALIDATION_ERROR', 'message' => __('api.message_cannot_send_to_self')]];
            return [];
        }

        $tenantId = app('tenant.id');

        // Check if sender is suspended/banned
        $sender = User::withoutGlobalScopes()
            ->where('id', $senderId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$sender || in_array($sender->status ?? 'active', ['suspended', 'banned', 'deactivated'])) {
            self::$errors = [['code' => 'FORBIDDEN', 'message' => __('api.message_sender_not_allowed')]];
            return [];
        }

        // Check if receiver exists in the same tenant
        $receiver = User::withoutGlobalScopes()
            ->where('id', $receiverId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$receiver) {
            self::$errors = [['code' => 'NOT_FOUND', 'message' => __('api.message_recipient_not_found')]];
            return [];
        }

        // Check if messaging is disabled for sender (broker restriction)
        $isDisabled = DB::table('user_messaging_restrictions')
            ->where('user_id', $senderId)
            ->where('tenant_id', $tenantId)
            ->where('messaging_disabled', true)
            ->exists();
        if ($isDisabled) {
            self::$errors = [['code' => 'MESSAGING_DISABLED', 'message' => __('api.message_messaging_restricted')]];
            return [];
        }

        // Recipient-side safeguarding can block direct contact — either the member
        // requires coordinator-mediated contact, or the interaction requires vetting
        // the sender does not hold. The gate is evaluated server-side by the same
        // method that powers the preflight notice shown when the conversation opens.
        // When it blocks HERE — on an actual send attempt — the message is not stored
        // and staff are alerted. Merely opening the conversation never alerts staff.
        $safeguardingGate = self::evaluateSafeguardingContactGate($senderId, $receiverId, $tenantId);
        if ($safeguardingGate !== null) {
            self::dispatchSafeguardingContactAttemptBlocked(
                $tenantId,
                $senderId,
                $receiverId,
                $safeguardingGate['code'],
                $safeguardingGate['required_vetting_types'],
                $safeguardingGate['required_vetting_labels'],
            );
            self::$errors = [self::buildSafeguardingError($safeguardingGate)];
            return [];
        }

        // Check if either user has blocked the other in this tenant.
        $blocked = DB::table('user_blocks')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($senderId, $receiverId) {
                $q->where(function ($inner) use ($senderId, $receiverId) {
                    $inner->where('user_id', $senderId)->where('blocked_user_id', $receiverId);
                })->orWhere(function ($inner) use ($senderId, $receiverId) {
                    $inner->where('user_id', $receiverId)->where('blocked_user_id', $senderId);
                });
            })
            ->exists();
        if ($blocked) {
            self::$errors = [['code' => 'BLOCKED', 'message' => __('api.message_blocked_user')]];
            return [];
        }

        // Server-side XSS prevention: strip all HTML from messages (plain text only)
        $content = \App\Helpers\HtmlSanitizer::stripAll(trim($data['body'] ?? ($data['content'] ?? '')));
        $voiceUrl = $data['voice_url'] ?? ($data['audio_url'] ?? null);
        $isVoice = !empty($data['is_voice']) || !empty($voiceUrl);

        // File/image attachments: the caller (controller) has already validated +
        // stored each file and passes metadata rows [url,name,size,mime]. A message
        // may carry attachments with no text body (e.g. "here's the photo").
        $attachments = [];
        if (is_array($data['attachments'] ?? null)) {
            foreach ($data['attachments'] as $att) {
                $url = is_array($att) ? trim((string) ($att['url'] ?? '')) : '';
                if ($url === '') {
                    continue;
                }
                $mime = $att['mime'] ?? null;
                $attachments[] = [
                    'url'  => $url,
                    'path' => isset($att['path']) && (string) $att['path'] !== '' ? (string) $att['path'] : $url,
                    'name' => mb_substr((string) ($att['name'] ?? 'attachment'), 0, 255),
                    'size' => isset($att['size']) ? (int) $att['size'] : null,
                    'mime' => $mime,
                    'type' => $att['type'] ?? (is_string($mime) && str_starts_with($mime, 'image/') ? 'image' : 'file'),
                ];
            }
        }
        $hasAttachments = count($attachments) > 0;

        if (empty($content) && !$isVoice && !$hasAttachments) {
            self::$errors = [['code' => 'VALIDATION_ERROR', 'message' => __('api.message_body_required')]];
            return [];
        }

        $attributes = [
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId,
            'body'           => $content,
            'is_read'        => false,
            'created_at'     => now(),
        ];

        // Voice message fields
        if ($isVoice && $voiceUrl) {
            $attributes['is_voice'] = true;
            $attributes['audio_url'] = $voiceUrl;
            if (!empty($data['audio_duration'])) {
                $attributes['audio_duration'] = (int) $data['audio_duration'];
            }
        }

        // Pass through contextual messaging fields if provided
        if (!empty($data['context_type'])) {
            $attributes['context_type'] = $data['context_type'];
        }
        if (!empty($data['context_id'])) {
            $attributes['context_id'] = (int) $data['context_id'];
        }

        $message = new Message($attributes);

        $message->save();

        // Persist file/image attachment rows (tenant_id auto-filled by HasTenantScope).
        if ($hasAttachments) {
            foreach ($attachments as $att) {
                try {
                    \App\Models\MessageAttachment::create([
                        'message_id' => $message->id,
                        'file_url'   => $att['url'],
                        // file_path is NOT NULL in the message_attachments table
                        // (created by the 2026_02_07 legacy migration) — must be set.
                        'file_path'  => $att['path'] ?? $att['url'],
                        'file_name'  => $att['name'],
                        'file_type'  => $att['type'] ?? 'file',
                        'file_size'  => $att['size'],
                        'mime_type'  => $att['mime'],
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Message attachment persist failed', ['error' => $e->getMessage(), 'message_id' => $message->id]);
                }
            }
        }

        // Broadcast the new message event for real-time delivery
        try {
            $sender = User::withoutGlobalScopes()->find($senderId);
            if ($sender) {
                $ids = [$senderId, $receiverId];
                sort($ids);
                $conversationId = crc32(implode('-', $ids));

                MessageSent::dispatch($message, $sender, $conversationId, $message->tenant_id ?? app('tenant.id'));
            }
        } catch (\Throwable $e) {
            Log::warning('MessageSent broadcast failed', ['error' => $e->getMessage(), 'message_id' => $message->id]);
        }

        return $message->fresh(['sender', 'receiver', 'attachments'])->toArray();
    }

    /**
     * Record an explicit request for coordinator-mediated contact.
     *
     * This is the action behind the "Request coordinator help" button in the
     * safeguarding panel. Unlike opening the conversation — which only renders the
     * preflight notice and never alerts staff — this IS an explicit contact attempt:
     * it alerts brokers/admins via SafeguardingCoordinationRequested and is audit-logged.
     *
     * The safeguarding gate is re-evaluated server-side so a client cannot trigger an
     * alert for a member who is not actually restricted.
     *
     * @return array{success: bool, code: string}|array{} Empty array on failure (inspect getErrors()).
     */
    public static function requestCoordinatorAssistance(int $senderId, int $recipientId): array
    {
        self::$errors = [];
        $tenantId = app('tenant.id');

        if ($recipientId <= 0 || $senderId === $recipientId) {
            self::$errors = [['code' => 'VALIDATION_ERROR', 'message' => __('api.message_recipient_required')]];
            return [];
        }

        $recipient = User::withoutGlobalScopes()
            ->where('id', $recipientId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$recipient) {
            self::$errors = [['code' => 'NOT_FOUND', 'message' => __('api.message_recipient_not_found')]];
            return [];
        }

        // Server-authoritative: only a genuinely restricted recipient warrants a staff alert.
        $gate = self::evaluateSafeguardingContactGate($senderId, $recipientId, $tenantId);
        if ($gate === null) {
            self::$errors = [[
                'code' => 'SAFEGUARDING_NOT_RESTRICTED',
                'message' => __('safeguarding.errors.coordination_not_required'),
            ]];
            return [];
        }

        // Alert staff. De-duplication of *delivery* (so a member tapping repeatedly does
        // not e-mail staff twice) is handled inside NotifySafeguardingCoordinationRequested
        // via its own claim/handled cache keys, which are only marked "done" AFTER a
        // successful delivery. We therefore dispatch on every request rather than
        // pre-claiming a window here: a failed delivery (queued listener, flaky mailer,
        // tenant with no staff) must NOT leave the member with a false success and a
        // suppressed retry — their next request re-dispatches and the listener re-attempts.
        try {
            event(new SafeguardingCoordinationRequested(
                tenantId: $tenantId,
                senderId: $senderId,
                recipientId: $recipientId,
                reasonCode: $gate['code'],
                requiredVettingTypes: $gate['required_vetting_types'],
                requiredVettingLabels: $gate['required_vetting_labels'],
            ));
        } catch (\Throwable $e) {
            Log::critical('MessageService::requestCoordinatorAssistance failed to dispatch coordination alert', [
                'tenant_id' => $tenantId,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'reason_code' => $gate['code'],
                'error' => $e->getMessage(),
            ]);
            self::$errors = [[
                'code' => 'COORDINATION_REQUEST_FAILED',
                'message' => __('safeguarding.errors.coordination_request_failed'),
            ]];
            return [];
        }

        self::auditCoordinationRequest($tenantId, $senderId, $recipientId, $gate);

        return ['success' => true, 'code' => $gate['code']];
    }

    /**
     * Mark all messages from a partner as read.
     */
    public static function markAsRead(int $partnerId, int $userId): int
    {
        return Message::query()
            ->where('sender_id', $partnerId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->where('is_federated', 0)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Get total unread message count for a user.
     */
    public static function getUnreadCount(int $userId): int
    {
        $query = Message::query()
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->where('is_federated', 0);

        self::applyReceiverUnreadVisibilityFilters($query);

        return $query->count();
    }

    // -----------------------------------------------------------------
    //  Validation errors
    // -----------------------------------------------------------------

    /** @var array */
    private static array $errors = [];

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * @param string[] $vettingTypes
     * @return string[]
     */
    private static function vettingTypeLabels(array $vettingTypes): array
    {
        $labels = [];
        foreach (array_values(array_unique($vettingTypes)) as $type) {
            if (!is_string($type) || $type === '') {
                continue;
            }

            $key = 'safeguarding.vetting_types.' . $type;
            $label = __($key);
            $labels[] = $label === $key
                ? ucwords(str_replace('_', ' ', $type))
                : $label;
        }

        return $labels;
    }

    /**
     * @param string[] $requiredVettingTypes
     * @param string[] $requiredVettingLabels
     */
    private static function dispatchSafeguardingContactAttemptBlocked(
        int $tenantId,
        int $senderId,
        int $recipientId,
        string $reasonCode,
        array $requiredVettingTypes = [],
        array $requiredVettingLabels = []
    ): void {
        try {
            event(new SafeguardingContactAttemptBlocked(
                tenantId: $tenantId,
                senderId: $senderId,
                recipientId: $recipientId,
                reasonCode: $reasonCode,
                requiredVettingTypes: $requiredVettingTypes,
                requiredVettingLabels: $requiredVettingLabels,
            ));
        } catch (\Throwable $e) {
            Log::critical('MessageService::send failed to dispatch safeguarding contact-attempt alert', [
                'tenant_id' => $tenantId,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'reason_code' => $reasonCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Evaluate recipient-side safeguarding contact gating for a sender → recipient pair.
     *
     * Pure read: never dispatches events, sends notifications, or writes audit rows.
     * Shared by send() (block + alert on a real send attempt), getConversation() (render
     * the preflight notice when the page opens — no alert), and requestCoordinatorAssistance()
     * (confirm a restriction exists before alerting staff).
     *
     * @return array{code: string, required_vetting_types: string[], required_vetting_labels: string[]}|null
     *         Null when direct contact is permitted.
     */
    public static function evaluateSafeguardingContactGate(int $senderId, int $recipientId, int $tenantId): ?array
    {
        // 1. Coordinator-mediated contact required (recipient opted into restricted messaging).
        try {
            if (SafeguardingTriggerService::isMessagingRestricted($recipientId, $tenantId)) {
                return [
                    'code' => 'SAFEGUARDING_CONTACT_RESTRICTED',
                    'required_vetting_types' => [],
                    'required_vetting_labels' => [],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('MessageService safeguarding contact-restriction lookup failed (continuing)', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipientId,
            ]);
        }

        // 2. Interaction requires vetting the sender does not hold.
        try {
            $recipientVettingTypes = SafeguardingTriggerService::getRequiredVettingTypes($recipientId, $tenantId);
            if (!empty($recipientVettingTypes)
                && !app(VettingService::class)->userHasAllValidVettings($senderId, $recipientVettingTypes)) {
                return [
                    'code' => 'VETTING_REQUIRED',
                    'required_vetting_types' => array_values($recipientVettingTypes),
                    'required_vetting_labels' => self::vettingTypeLabels($recipientVettingTypes),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('MessageService safeguarding vetting lookup failed (continuing)', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipientId,
            ]);
        }

        return null;
    }

    /**
     * Build the structured, translated error/notice payload for a safeguarding gate result.
     *
     * Shared by send() (validation error array) and getConversation() (preflight notice) so the
     * panel content is identical whether it appears on load or after a blocked send attempt.
     *
     * @param array{code: string, required_vetting_types: string[], required_vetting_labels: string[]} $gate
     * @return array<string, mixed>
     */
    public static function buildSafeguardingError(array $gate): array
    {
        if (($gate['code'] ?? null) === 'VETTING_REQUIRED') {
            $labels = array_values($gate['required_vetting_labels'] ?? []);
            $typesString = implode(', ', $labels);

            return [
                'code' => 'VETTING_REQUIRED',
                'message' => __('safeguarding.errors.vetting_required', ['types' => $typesString]),
                'title' => __('safeguarding.errors.vetting_required_title'),
                'detail' => __('safeguarding.errors.vetting_required_detail', ['types' => $typesString]),
                'action_label' => __('safeguarding.errors.vetting_required_action'),
                'required_vetting_types' => array_values($gate['required_vetting_types'] ?? []),
                'required_vetting_labels' => $labels,
            ];
        }

        return [
            'code' => 'SAFEGUARDING_CONTACT_RESTRICTED',
            'message' => __('safeguarding.errors.contact_restricted'),
            'title' => __('safeguarding.errors.contact_restricted_title'),
            'detail' => __('safeguarding.errors.contact_restricted_detail'),
            'action_label' => __('safeguarding.errors.contact_restricted_action'),
        ];
    }

    /**
     * Audit an explicit coordinator-assistance request (consistent with the
     * safeguarding trigger-activation audit trail). Failures never block the request.
     *
     * @param array{code: string, required_vetting_types: string[], required_vetting_labels: string[]} $gate
     */
    private static function auditCoordinationRequest(int $tenantId, int $senderId, int $recipientId, array $gate): void
    {
        try {
            DB::table('activity_log')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $senderId,
                'action' => 'safeguarding_coordination_requested',
                'action_type' => 'safeguarding',
                'entity_type' => 'user',
                'entity_id' => $recipientId,
                'details' => json_encode([
                    'reason_code' => $gate['code'],
                    'required_vetting_types' => array_values($gate['required_vetting_types'] ?? []),
                ]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log safeguarding coordination request', ['error' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------
    //  Conversation summary
    // -----------------------------------------------------------------

    /**
     * Get a single conversation summary with another user.
     */
    public static function getConversation(int $otherUserId, int $userId): ?array
    {
        self::$errors = [];

        $tenantId = app('tenant.id');

        // Verify user exists within the same tenant (not cross-tenant)
        $otherUser = User::withoutGlobalScopes()
            ->where('id', $otherUserId)
            ->where('tenant_id', $tenantId)
            ->first();
        if (! $otherUser) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.user_not_found')];
            return null;
        }

        $unreadQuery = Message::query()
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->where('is_federated', 0);

        self::applyReceiverUnreadVisibilityFilters($unreadQuery);
        $unreadCount = $unreadQuery->count();

        $messageCount = Message::query()
            ->betweenUsers($userId, $otherUserId)
            ->where('is_federated', 0)
            ->count();

        // Preflight safeguarding state: surfaced so the conversation page can show the
        // restriction notice and disable the composer the moment it opens — BEFORE the
        // member types. This is a pure read; it never alerts staff. Only an actual send
        // attempt (MessageService::send) or an explicit "Request coordinator help" action
        // (MessageService::requestCoordinatorAssistance) notifies brokers/admins.
        $safeguarding = null;
        $gate = self::evaluateSafeguardingContactGate($userId, $otherUserId, $tenantId);
        if ($gate !== null) {
            $error = self::buildSafeguardingError($gate);
            $safeguarding = [
                'restricted'              => true,
                'code'                    => $error['code'],
                'title'                   => $error['title'] ?? null,
                'message'                 => $error['message'] ?? null,
                'detail'                  => $error['detail'] ?? null,
                'action_label'            => $error['action_label'] ?? null,
                'required_vetting_types'  => $error['required_vetting_types'] ?? [],
                'required_vetting_labels' => $error['required_vetting_labels'] ?? [],
                'can_request_coordinator' => true,
            ];
        }

        return [
            'id' => $otherUserId,
            'other_user' => [
                'id'         => $otherUser->id,
                'name'       => $otherUser->name ?? trim(($otherUser->first_name ?? '') . ' ' . ($otherUser->last_name ?? '')),
                'first_name' => $otherUser->first_name,
                'last_name'  => $otherUser->last_name,
                'avatar_url' => $otherUser->avatar_url,
                'is_online'  => ($otherUser->last_active_at && $otherUser->last_active_at->gt(now()->subMinutes(5))),
            ],
            'unread_count'  => $unreadCount,
            'message_count' => $messageCount,
            'safeguarding'  => $safeguarding,
        ];
    }

    // -----------------------------------------------------------------
    //  Schema introspection cache (avoids INFORMATION_SCHEMA queries per request)
    // -----------------------------------------------------------------

    /** @var bool|null Cached result of schema introspection for archived columns */
    private static ?bool $hasArchivedColumns = null;

    /** @var bool|null Cached result of schema introspection for is_deleted column */
    private static ?bool $hasDeletedColumn = null;

    /** @var bool|null Cached result of schema introspection for reactions column */
    private static ?bool $hasReactionsColumn = null;

    /** @var bool|null Cached result of schema introspection for per-user delete columns */
    private static ?bool $hasPerUserDeleteColumns = null;

    /**
     * Keep unread counts aligned with messages that are visible in the receiver's inbox.
     */
    private static function applyReceiverUnreadVisibilityFilters($query)
    {
        if (self::hasArchivedColumns()) {
            $query->whereNull('archived_by_receiver');
        }

        if (self::hasPerUserDeleteColumns()) {
            $query->where('is_deleted_receiver', false);
        }

        if (self::hasDeletedColumn()) {
            $query->where(function ($inner) {
                $inner->where('is_deleted', false)->orWhereNull('is_deleted');
            });
        }

        return $query;
    }

    /**
     * Check if messages table has archived columns (cached per-request).
     */
    private static function hasArchivedColumns(): bool
    {
        if (self::$hasArchivedColumns === null) {
            self::$hasArchivedColumns = DB::getSchemaBuilder()->hasColumn('messages', 'archived_by_sender');
        }
        return self::$hasArchivedColumns;
    }

    /**
     * Check if messages table has is_deleted column (cached per-request).
     */
    private static function hasDeletedColumn(): bool
    {
        if (self::$hasDeletedColumn === null) {
            self::$hasDeletedColumn = DB::getSchemaBuilder()->hasColumn('messages', 'is_deleted');
        }
        return self::$hasDeletedColumn;
    }

    /**
     * Check if messages table has reactions column (cached per-request).
     */
    private static function hasReactionsColumn(): bool
    {
        if (self::$hasReactionsColumn === null) {
            self::$hasReactionsColumn = DB::getSchemaBuilder()->hasColumn('messages', 'reactions');
        }
        return self::$hasReactionsColumn;
    }

    /**
     * Check if messages table has per-user delete columns (cached per-request).
     */
    private static function hasPerUserDeleteColumns(): bool
    {
        if (self::$hasPerUserDeleteColumns === null) {
            self::$hasPerUserDeleteColumns = DB::getSchemaBuilder()->hasColumn('messages', 'is_deleted_sender');
        }
        return self::$hasPerUserDeleteColumns;
    }

    // -----------------------------------------------------------------
    //  Archive / Unarchive
    // -----------------------------------------------------------------

    /**
     * Archive a conversation with another user.
     *
     * @param string $scope 'self'     — hides from current user's inbox only (restorable).
     *                      'everyone' — hides from both users' inboxes.
     *
     * Uses per-user archival columns so each user can independently archive.
     */
    public static function archiveConversation(int $otherUserId, int $userId, string $scope = 'self'): int
    {
        $tenantId = app('tenant.id');
        $now = now();
        $totalUpdated = 0;

        if (! self::hasArchivedColumns()) {
            // Fall back to hard delete if columns don't exist
            return DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId, $otherUserId) {
                    $q->where(function ($q2) use ($userId, $otherUserId) {
                        $q2->where('sender_id', $userId)->where('receiver_id', $otherUserId);
                    })->orWhere(function ($q2) use ($userId, $otherUserId) {
                        $q2->where('sender_id', $otherUserId)->where('receiver_id', $userId);
                    });
                })
                ->delete();
        }

        if ($scope === 'everyone') {
            // Hide from both users' inboxes in one pass
            $totalUpdated += DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $userId)
                ->where('receiver_id', $otherUserId)
                ->update(['archived_by_sender' => $now, 'archived_by_receiver' => $now]);

            $totalUpdated += DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $otherUserId)
                ->where('receiver_id', $userId)
                ->update(['archived_by_sender' => $now, 'archived_by_receiver' => $now]);

            return $totalUpdated;
        }

        // scope = 'self': archive from current user's view only
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->where('receiver_id', $otherUserId)
            ->whereNull('archived_by_sender')
            ->update(['archived_by_sender' => $now]);

        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->whereNull('archived_by_receiver')
            ->update(['archived_by_receiver' => $now]);

        return $totalUpdated;
    }

    /**
     * Unarchive a conversation with another user.
     */
    public static function unarchiveConversation(int $otherUserId, int $userId): int
    {
        $tenantId = app('tenant.id');

        if (! self::hasArchivedColumns()) {
            return 0;
        }

        $totalUpdated = 0;

        // Unarchive messages where user is the sender
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->where('receiver_id', $otherUserId)
            ->whereNotNull('archived_by_sender')
            ->update(['archived_by_sender' => null]);

        // Unarchive messages where user is the receiver
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->whereNotNull('archived_by_receiver')
            ->update(['archived_by_receiver' => null]);

        return $totalUpdated;
    }

    // -----------------------------------------------------------------
    //  Edit / Delete messages
    // -----------------------------------------------------------------

    /**
     * Edit a message body.
     */
    public static function editMessage(int $messageId, int $userId, string $newBody): ?array
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.message_not_found')];
            return null;
        }

        if ((int) $message->sender_id !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.message_edit_own_only')];
            return null;
        }

        // Enforce 24-hour edit window
        if ($message->created_at && $message->created_at->lt(now()->subHours(24))) {
            self::$errors[] = ['code' => 'EDIT_EXPIRED', 'message' => __('api.message_edit_window_expired')];
            return null;
        }

        // Server-side XSS prevention: strip all HTML (consistent with send())
        $newBody = \App\Helpers\HtmlSanitizer::stripAll($newBody);

        $message->body = $newBody;

        if (in_array('is_edited', $message->getFillable(), true)) {
            $message->is_edited = true;
            $message->edited_at = now();
        }

        $message->save();

        return [
            'id'         => $message->id,
            'body'       => $newBody,
            'is_edited'  => true,
            'sender_id'  => (int) $message->sender_id,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * Delete a message (soft delete).
     *
     * @param string $scope 'everyone' — blanks body, shows placeholder to both parties (sender or receiver can do this).
     *                      'self'     — hides message from current user's view only; other party unaffected.
     */
    public static function deleteMessage(int $messageId, int $userId, string $scope = 'everyone'): bool
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.message_not_found')];
            return false;
        }

        $isSender   = (int) $message->sender_id   === $userId;
        $isReceiver = (int) $message->receiver_id === $userId;

        if (! $isSender && ! $isReceiver) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.message_not_participant')];
            return false;
        }

        $tenantId = app('tenant.id');

        if ($scope === 'self') {
            // Per-user hide: only affects current user's view, body unchanged
            if ($isSender) {
                DB::table('messages')
                    ->where('id', $messageId)
                    ->where('tenant_id', $tenantId)
                    ->update(['is_deleted_sender' => true]);
            } else {
                DB::table('messages')
                    ->where('id', $messageId)
                    ->where('tenant_id', $tenantId)
                    ->update(['is_deleted_receiver' => true]);
            }

            return true;
        }

        // scope = 'everyone': blank body for both parties
        if (self::hasDeletedColumn()) {
            DB::table('messages')
                ->where('id', $messageId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'is_deleted'  => true,
                    'body'        => '[Message deleted]',
                    'reactions'   => null,
                    'deleted_at'  => now(),
                ]);
        } else {
            $message->delete();
        }

        return true;
    }

    // -----------------------------------------------------------------
    //  Reactions
    // -----------------------------------------------------------------

    /**
     * Toggle a reaction emoji on a message.
     *
     * Writes to both the `message_reactions` table (for proper per-user tracking)
     * and the `reactions` JSON column on `messages` (for backward compatibility).
     *
     * @return bool|null True if added, false if removed, null on error
     */
    public static function toggleReaction(int $messageId, int $userId, string $emoji): ?bool
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => __('api.message_not_found')];
            return null;
        }

        // User must be sender or receiver (1-to-1) or a conversation participant (group)
        $isParticipant = false;
        if ((int) $message->sender_id === $userId || (int) $message->receiver_id === $userId) {
            $isParticipant = true;
        } elseif ($message->conversation_id) {
            $isParticipant = DB::table('conversation_participants')
                ->where('conversation_id', $message->conversation_id)
                ->where('user_id', $userId)
                ->whereNull('left_at')
                ->exists();
        }

        if (!$isParticipant) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => __('api.message_cannot_react')];
            return null;
        }

        $tenantId = app('tenant.id');

        return DB::transaction(function () use ($messageId, $userId, $emoji, $tenantId) {
            // Toggle in message_reactions table
            $existing = DB::table('message_reactions')
                ->where('message_id', $messageId)
                ->where('user_id', $userId)
                ->where('emoji', $emoji)
                ->first();

            $wasAdded = false;
            if ($existing) {
                DB::table('message_reactions')->where('id', $existing->id)->delete();
            } else {
                DB::table('message_reactions')->insert([
                    'tenant_id' => $tenantId,
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'emoji' => $emoji,
                    'created_at' => now(),
                ]);
                $wasAdded = true;
            }

            // Also update the JSON reactions column for backward compatibility
            if (self::hasReactionsColumn()) {
                $reactions = [];
                $rawReactions = DB::table('messages')->where('id', $messageId)->lockForUpdate()->value('reactions');
                if (! empty($rawReactions)) {
                    $reactions = json_decode($rawReactions, true) ?? [];
                }

                $userReactions = $reactions['_users'] ?? [];
                $userKey = "{$userId}_{$emoji}";

                if ($wasAdded) {
                    $userReactions[$userKey] = true;
                    $reactions[$emoji] = ($reactions[$emoji] ?? 0) + 1;
                } else {
                    unset($userReactions[$userKey]);
                    if (isset($reactions[$emoji]) && $reactions[$emoji] > 0) {
                        $reactions[$emoji]--;
                        if ($reactions[$emoji] <= 0) {
                            unset($reactions[$emoji]);
                        }
                    }
                }

                $reactions['_users'] = $userReactions;

                DB::table('messages')
                    ->where('id', $messageId)
                    ->where('tenant_id', $tenantId)
                    ->update(['reactions' => json_encode($reactions)]);
            }

            return $wasAdded;
        });
    }

    // -----------------------------------------------------------------
    //  Typing indicator
    // -----------------------------------------------------------------

    /**
     * Set typing indicator for a conversation.
     *
     * Broadcasts a typing event to the recipient's private Pusher channel.
     * No DB persistence needed — purely real-time.
     */
    public static function setTypingIndicator(int $recipientId, int $userId, bool $isTyping): bool
    {
        try {
            $tenantId = app('tenant.id');
            $channelName = "private-tenant.{$tenantId}.user.{$recipientId}";

            // Broadcast typing event via the configured broadcast driver (Pusher).
            // Uses the broadcast manager to resolve the underlying Pusher connection.
            $broadcaster = app('Illuminate\Broadcasting\BroadcastManager');
            $driver = $broadcaster->connection('pusher');

            // The Pusher broadcaster wraps the Pusher SDK; access it to trigger directly.
            if (method_exists($driver, 'getPusher')) {
                $driver->getPusher()->trigger($channelName, 'typing', [
                    'user_id'   => $userId,
                    'is_typing' => $isTyping,
                ]);
            }
        } catch (\Throwable $e) {
            Log::debug('Typing indicator broadcast failed', ['error' => $e->getMessage()]);
        }

        return true;
    }
}
