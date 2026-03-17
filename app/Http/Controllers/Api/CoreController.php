<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Services\BrokerMessageVisibilityService;

/**
 * CoreController -- Core platform endpoints (members, listings, groups,
 * messages, notifications).
 *
 * Converted from legacy delegation to DB facade / static services.
 */
class CoreController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ──────────────────────────────────────────────
    // Messaging endpoints
    // ──────────────────────────────────────────────

    /** POST /api/messages/send */
    public function sendMessage(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('send_message', 30, 60);

        $receiverId = $this->inputInt('receiver_id', 0, 1);
        $body = trim($this->input('body', ''));

        if (!$receiverId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Missing receiver_id', 'receiver_id', 400);
        }
        if (empty($body)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message body is required', 'body', 400);
        }
        if ($receiverId === $userId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Cannot send message to yourself', 'receiver_id', 400);
        }

        if (BrokerMessageVisibilityService::isMessagingDisabledForUser($userId)) {
            return $this->respondWithError('SENDER_RESTRICTED', 'Your messaging privileges have been restricted. Please contact support.', null, 403);
        }
        if (BrokerMessageVisibilityService::isMessagingDisabledForUser($receiverId)) {
            return $this->respondWithError('RECIPIENT_UNAVAILABLE', 'This user is not currently accepting messages.', null, 403);
        }

        $tenantId = $this->getTenantId();

        try {
            DB::insert(
                "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $userId, $receiverId, $body]
            );
            $messageId = (int) DB::getPdo()->lastInsertId();

            $message = (array) DB::selectOne(
                "SELECT id, sender_id, receiver_id, body, created_at FROM messages WHERE id = ?",
                [$messageId]
            );

            // Pusher broadcast (non-blocking)
            if (class_exists('Nexus\Services\RealtimeService')) {
                try {
                    \Nexus\Services\RealtimeService::broadcastMessage($userId, $receiverId, $message);
                } catch (\Exception $e) {
                    error_log("Pusher notification failed: " . $e->getMessage());
                }
            }

            // In-app notification + email (non-blocking)
            try {
                $sender = DB::selectOne("SELECT name FROM users WHERE id = ?", [$userId]);
                $senderName = $sender->name ?? 'Someone';

                if ($sender && class_exists('Nexus\Models\Notification')) {
                    \Nexus\Models\Notification::create(
                        $receiverId,
                        "New message from " . $senderName,
                        "/messages/" . $userId,
                        'message'
                    );
                }

                $preview = mb_strlen($body) > 50 ? mb_substr($body, 0, 47) . '...' : $body;
                \Nexus\Models\Message::sendEmailNotification($receiverId, $senderName, $preview, $userId);
            } catch (\Exception $e) {
                error_log("Message notification failed: " . $e->getMessage());
            }

            return $this->respondWithData($message, null, 201);
        } catch (\Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to send message', null, 500);
        }
    }

    /** POST /api/messages/typing */
    public function typing(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('typing', 60, 60);

        $receiverId = $this->inputInt('receiver_id', 0, 1);

        if (!$receiverId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Missing receiver_id', 'receiver_id', 400);
        }

        if (class_exists('Nexus\Services\RealtimeService')) {
            try {
                \Nexus\Services\RealtimeService::broadcastTyping($userId, $receiverId, true);
                return $this->respondWithData(['note' => 'Typing broadcast']);
            } catch (\Exception $e) {
                error_log("Pusher typing notification failed: " . $e->getMessage());
                return $this->respondWithData(['note' => 'Realtime disabled']);
            }
        }

        return $this->respondWithData(['note' => 'Realtime not configured']);
    }

    /** GET /api/messages/poll */
    public function pollMessages(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('poll_messages', 120, 60);

        $otherUserId = $this->queryInt('other_user_id', 0, 1);
        $afterId = $this->queryInt('after', 0, 0);

        if (!$otherUserId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Missing other_user_id', 'other_user_id', 400);
        }

        $query = DB::table('messages')
            ->select('id', 'sender_id', 'body', 'audio_url', 'audio_duration', 'created_at')
            ->where(function ($q) use ($userId, $otherUserId) {
                $q->where(function ($inner) use ($userId, $otherUserId) {
                    $inner->where('sender_id', $userId)->where('receiver_id', $otherUserId);
                })->orWhere(function ($inner) use ($userId, $otherUserId) {
                    $inner->where('sender_id', $otherUserId)->where('receiver_id', $userId);
                });
            });

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->orderBy('id', 'asc')->limit(50)->get()->map(fn ($m) => (array) $m)->all();

        return $this->respondWithData($messages);
    }

    /** GET /api/messages/unread-count */
    public function unreadMessagesCount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('unread_messages', 120, 60);

        $count = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $count += (int) $thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $count = 0;
        }

        return $this->respondWithData(['count' => $count]);
    }

    // ──────────────────────────────────────────────
    // Contact form
    // ──────────────────────────────────────────────

    /** POST /api/contact */
    public function apiSubmit(): JsonResponse
    {
        $this->rateLimit('contact_form', 5, 60);

        $name = trim($this->input('name', ''));
        $email = trim($this->input('email', ''));
        $subject = trim($this->input('subject', 'General Inquiry'));
        $message = trim($this->input('message', ''));

        $errors = [];
        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (empty($message)) $errors[] = 'Message is required.';

        if (!empty($errors)) {
            return response()->json(['success' => false, 'error' => implode(' ', $errors)], 400);
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';
        $tenantEmail = $tenant['contact_email'] ?? '';

        if (empty($tenantEmail)) {
            return response()->json(['success' => false, 'error' => 'No contact email configured for this community.'], 500);
        }

        $emailSubject = "[{$tenantName}] Contact Form: {$subject}";
        $emailBody = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

        $sent = false;
        try {
            $mailer = new Mailer();
            $replyTo = "{$name} <{$email}>";
            $sent = $mailer->send($tenantEmail, $emailSubject, $emailBody, null, $replyTo);
        } catch (\Exception $e) {
            error_log("Contact form email error: " . $e->getMessage());
        }

        // Log submission
        try {
            DB::insert(
                "INSERT INTO contact_submissions (tenant_id, name, email, subject, message, email_sent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [TenantContext::getId(), $name, $email, $subject, $message, $sent ? 1 : 0]
            );
        } catch (\Throwable $e) {
            // Table may not exist — non-critical
        }

        return response()->json(['success' => true, 'message' => $sent ? 'Message sent successfully.' : "Message received. We'll get back to you soon."]);
    }

    // ──────────────────────────────────────────────
    // Members, Listings, Groups — converted to DB facade
    // ──────────────────────────────────────────────

    /** GET /api/members */
    public function members(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('members', 60, 60);

        $tenantId = $this->getTenantId();
        $searchQuery = trim($this->query('q', ''));
        $activeOnly = $this->query('active') === 'true';
        $limit = $this->queryInt('limit', 100, 1, 500);
        $offset = $this->queryInt('offset', 0, 0);

        $builder = DB::table('users')
            ->select('id', 'name', 'email', 'avatar_url as avatar', 'role', 'bio', 'location', 'last_active_at')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('avatar_url')
            ->whereRaw('LENGTH(avatar_url) > 0');

        if (!empty($searchQuery) && strlen($searchQuery) >= 2) {
            $term = "%{$searchQuery}%";
            $builder->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('bio', 'like', $term)
                  ->orWhere('location', 'like', $term);
            });
        }

        if ($activeOnly) {
            $builder->whereRaw('last_active_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
        }

        $totalCount = $builder->count();

        $members = $builder
            ->orderByDesc('last_active_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($m) => (array) $m)
            ->all();

        $page = ($offset / max($limit, 1)) + 1;

        return $this->respondWithPaginatedCollection($members, $totalCount, (int) $page, $limit);
    }

    /** GET /api/listings */
    public function listings(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('listings', 60, 60);

        $listings = DB::table('listings')
            ->select('id', 'title', 'description', 'price', 'type', 'created_at', 'image_url as image', 'user_id')
            ->where('tenant_id', $this->getTenantId())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => (array) $l)
            ->all();

        return $this->respondWithData($listings);
    }

    /** GET /api/groups */
    public function groups(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('groups', 60, 60);

        $groups = DB::table('groups')
            ->select('id', 'name', 'description', 'image_url as image')
            ->where('tenant_id', $this->getTenantId())
            ->get()
            ->map(fn ($g) => (array) $g)
            ->all();

        foreach ($groups as &$g) {
            $g['members'] = (int) DB::table('group_members')
                ->where('group_id', $g['id'])
                ->count();
        }

        return $this->respondWithData($groups);
    }

    /** GET /api/messages */
    public function messages(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages', 60, 60);

        $messages = DB::table('messages as m')
            ->join('users as u', 'm.sender_id', '=', 'u.id')
            ->select('m.*', 'u.name as sender_name', 'u.avatar_url as sender_avatar')
            ->where('m.recipient_id', $userId)
            ->groupBy('m.sender_id')
            ->orderByDesc('m.created_at')
            ->get()
            ->map(fn ($m) => (array) $m)
            ->all();

        return $this->respondWithData($messages);
    }

    /** GET /api/notifications */
    public function notifications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('notifications', 120, 60);

        $notifs = DB::table('notifications')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => (array) $n)
            ->all();

        return $this->respondWithData($notifs);
    }

    /** GET /api/notifications/check */
    public function checkNotifications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('check_notifications', 120, 60);

        $count = \Nexus\Models\Notification::countUnread($userId);

        return $this->respondWithData(['unread_count' => $count]);
    }

    /** GET /api/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('unread_count', 120, 60);

        $messagesCount = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $messagesCount += (int) $thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $messagesCount = 0;
        }

        $notificationsCount = \Nexus\Models\Notification::countUnread($userId);

        return $this->respondWithData([
            'messages' => $messagesCount,
            'notifications' => $notificationsCount,
            'total' => $messagesCount + $notificationsCount,
        ]);
    }

}
