<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\Mailer;
use App\Core\TenantContext;

/**
 * CoreController -- Contact form, members, listings, groups, notifications.
 *
 * Legacy messaging endpoints removed — all clients use /v2/messages (MessagesController).
 */
class CoreController extends BaseApiController
{
    protected bool $isV2Api = true;

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
            return $this->respondWithError('VALIDATION_ERROR', implode(' ', $errors), null, 400);
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';
        $tenantEmail = $tenant['contact_email'] ?? '';

        if (empty($tenantEmail)) {
            return $this->respondWithError('SERVER_ERROR', __('api.no_contact_email_configured'), null, 500);
        }

        $emailSubject = "[{$tenantName}] Contact Form: {$subject}";
        $emailBody = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";

        $sent = false;
        try {
            $mailer = Mailer::forCurrentTenant();
            $replyTo = "{$name} <{$email}>";
            $sent = $mailer->send($tenantEmail, $emailSubject, $emailBody, null, $replyTo);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Contact form email error: " . $e->getMessage());
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

        return $this->respondWithData(['message' => $sent ? __('api_controllers_1.contact_form.sent_successfully') : __('api_controllers_1.contact_form.received_fallback')]);
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

    /** GET /api/notifications */
    public function notifications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('notifications', 120, 60);

        $notifs = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('tenant_id', $this->getTenantId())
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

        $count = \App\Models\Notification::countUnread($userId);

        return $this->respondWithData(['unread_count' => $count]);
    }

    /** GET /api/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('unread_count', 120, 60);

        $messagesCount = 0;
        try {
            if (class_exists('App\Models\MessageThread')) {
                $threads = \App\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $messagesCount += (int) $thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $messagesCount = 0;
        }

        $notificationsCount = \App\Models\Notification::countUnread($userId);

        return $this->respondWithData([
            'messages' => $messagesCount,
            'notifications' => $notificationsCount,
            'total' => $messagesCount + $notificationsCount,
        ]);
    }

}
