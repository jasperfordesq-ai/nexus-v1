<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Models\User;
use App\Services\CommentService;
use App\Services\GoalCheckinService;
use App\Services\GoalProgressService;
use App\Services\GoalReminderService;
use App\Services\GoalService;
use App\Services\SocialNotificationService;
use App\Support\FeedItemTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Goals — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Closes React parity gaps that the core goals routes (in routes/govuk-alpha.php)
 * do not yet cover:
 *   - Goal Insights Panel (milestones, streaks, last/next check-in, buddy notes)
 *     mirrors GoalInsightsPanel.tsx + GET /api/v2/goals/{id}/insights.
 *   - Check-in logging (progress + mood + note) mirrors GoalCheckinModal.tsx +
 *     POST /api/v2/goals/{id}/checkins (owner-only, notifies the buddy).
 *   - Reminder settings (frequency + enable/disable + delete) mirror
 *     GoalReminderToggle.tsx + GET/PUT/DELETE /api/v2/goals/{id}/reminder.
 *   - Multiple buddy action types (nudge / encouragement / offer_help) mirror
 *     GoalsPage.tsx handleBuddyAction + POST /api/v2/goals/{id}/buddy/nudge.
 *
 * Every method calls the SAME service the React GoalsController calls; no
 * money/auth/notification logic is reimplemented. Controller-level
 * notifications are mirrored and wrapped in LocaleContext::withLocale().
 */
trait GoalsParity
{
    /** Allowed mood values (matches goal_checkins.mood enum + the React picker). */
    private const GOALS_PARITY_MOODS = [
        'great', 'good', 'neutral', 'okay', 'struggling', 'stuck', 'motivated', 'grateful',
    ];

    /** Allowed reminder frequencies (matches goal_reminders.frequency enum). */
    private const GOALS_PARITY_FREQUENCIES = ['daily', 'weekly', 'biweekly', 'monthly'];

    /** Allowed buddy action types this page lets a buddy send. */
    private const GOALS_PARITY_BUDDY_TYPES = ['nudge', 'encouragement', 'offer_help'];

    /**
     * Shared gate: assert tenant, require auth, require the goals feature, load
     * the goal tenant-scoped (404 if missing in this tenant). Returns the goal
     * array on success or a RedirectResponse (auth) — callers must handle both.
     *
     * @return array<string,mixed>|RedirectResponse
     */
    private function goalsParityLoad(string $tenantSlug, int $id): array|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('goals'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $goal = null;
        try {
            $goal = app(GoalService::class)->getById($id)?->toArray();
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($goal === null, 404);

        return $goal;
    }

    /** Whether a member may view a goal (owner, buddy/mentor, or public). */
    private function goalsParityCanView(array $goal, int $userId): bool
    {
        return (bool) ($goal['is_public'] ?? false)
            || (int) ($goal['user_id'] ?? 0) === $userId
            || ((int) ($goal['mentor_id'] ?? 0) === $userId && ($goal['mentor_id'] ?? null) !== null);
    }

    // === Insights panel ===

    /** Goal insights: milestones, streaks, check-in cadence, buddy notes. */
    public function goalsInsights(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        // Mirrors GoalsController::insights canViewGoal — private goals are 403
        // for anyone who is not the owner or the buddy.
        abort_unless($this->goalsParityCanView($goal, $userId), 403);

        $isOwner = (int) ($goal['user_id'] ?? 0) === $userId;
        $isBuddy = (int) ($goal['mentor_id'] ?? 0) === $userId && ($goal['mentor_id'] ?? null) !== null;

        $insights = [];
        try {
            $insights = app(GoalProgressService::class)->getInsights($id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::goals-insights', [
            'title' => __('govuk_alpha_goals.insights.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'insights' => is_array($insights) ? $insights : [],
            'isOwner' => $isOwner,
            'isBuddy' => $isBuddy,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // === Check-in (owner-only) ===

    /** Check-in form + recent check-in history (owner only). */
    public function goalsCheckin(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        // Mirrors GoalsController::createCheckin — only the owner may log check-ins.
        abort_unless((int) ($goal['user_id'] ?? 0) === $userId, 403);

        $checkins = [];
        try {
            $checkins = app(GoalCheckinService::class)->getByGoalId($id, ['limit' => 20])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        $cur = (float) ($goal['current_value'] ?? 0);
        $tgt = (float) ($goal['target_value'] ?? 0);
        $currentPercent = $tgt > 0 ? (int) round(min(100, max(0, ($cur / $tgt) * 100))) : 0;

        return $this->view('accessible-frontend::goals-checkin', [
            'title' => __('govuk_alpha_goals.checkin.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'checkins' => is_array($checkins) ? $checkins : [],
            'currentPercent' => $currentPercent,
            'moods' => self::GOALS_PARITY_MOODS,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Record a check-in (owner only). Notifies the buddy, mirroring the API. */
    public function goalsStoreCheckin(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        abort_unless((int) ($goal['user_id'] ?? 0) === $userId, 403);

        $progress = $request->input('progress_percent');
        $percent = $progress === null || $progress === '' ? null : (float) $progress;
        if ($percent !== null) {
            $percent = min(100.0, max(0.0, $percent));
        }

        $mood = self::asStr($request->input('mood'));
        if (! in_array($mood, self::GOALS_PARITY_MOODS, true)) {
            $mood = null;
        }

        $note = trim(self::asStr($request->input('note')));

        $ok = false;
        try {
            // Same service the React GoalsController::createCheckin calls.
            $checkin = app(GoalCheckinService::class)->create($id, $userId, [
                'progress_percent' => $percent,
                'progress_value' => $percent,
                'note' => $note !== '' ? mb_substr($note, 0, 2000) : null,
                'mood' => $mood,
            ]);
            $ok = $checkin !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            $this->goalsParityNotifyBuddyOfCheckin($id, $userId, $goal);
        }

        return redirect()->route('govuk-alpha.goals.checkin', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'checkin-recorded' : 'checkin-failed',
        ]);
    }

    /**
     * Mirror GoalsController::createCheckin's buddy notification, rendered in the
     * mentor's preferred language. Best-effort; never blocks the redirect.
     *
     * @param array<string,mixed> $goal
     */
    private function goalsParityNotifyBuddyOfCheckin(int $id, int $userId, array $goal): void
    {
        try {
            $mentorId = ($goal['mentor_id'] ?? null) !== null ? (int) $goal['mentor_id'] : null;
            if ($mentorId === null || $mentorId === $userId) {
                return;
            }
            $owner = User::find($userId);
            $mentor = User::find($mentorId);
            if ($mentor === null) {
                return;
            }
            $goalTitle = (string) ($goal['title'] ?? '');
            LocaleContext::withLocale($mentor, function () use ($owner, $goalTitle, $mentorId, $id): void {
                $ownerName = $owner->name ?? __('emails.common.fallback_someone');
                Notification::createNotification(
                    $mentorId,
                    __('api_controllers_3.goals.checkin_mentor', ['name' => $ownerName, 'title' => $goalTitle]),
                    "/goals/{$id}",
                    'goal_checkin'
                );
            });
        } catch (\Throwable $e) {
            Log::warning('Goal check-in notification failed (accessible)', ['goal' => $id, 'error' => $e->getMessage()]);
        }
    }

    // === Reminders ===

    /** Reminder settings page (owner, or anyone for a public goal — mirrors setReminder). */
    public function goalsReminder(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        // setReminder() allows the owner or any member for a public goal; reflect
        // that gate here so the form only renders when a save would succeed.
        $isOwner = (int) ($goal['user_id'] ?? 0) === $userId;
        abort_unless($isOwner || (bool) ($goal['is_public'] ?? false), 403);

        $reminder = null;
        try {
            $reminder = app(GoalReminderService::class)->getReminder($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::goals-reminder', [
            'title' => __('govuk_alpha_goals.reminder.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'reminder' => is_array($reminder) ? $reminder : null,
            'frequencies' => self::GOALS_PARITY_FREQUENCIES,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Create or update the reminder (PUT-equivalent). */
    public function goalsSaveReminder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();

        $frequency = self::asStr($request->input('frequency'));
        if (! in_array($frequency, self::GOALS_PARITY_FREQUENCIES, true)) {
            $frequency = 'weekly';
        }

        $result = [];
        try {
            // setReminder() enforces owner-or-public itself and returns [] if denied.
            $result = app(GoalReminderService::class)->setReminder($id, $userId, [
                'frequency' => $frequency,
                'enabled' => $request->boolean('enabled', true),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.goals.reminder', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $result === [] ? 'reminder-failed' : 'reminder-saved',
        ]);
    }

    /** Delete the caller's reminder for this goal. */
    public function goalsDeleteReminder(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();

        try {
            // deleteReminder() is filtered by (goal_id, user_id) so a member can
            // only ever remove their own reminder.
            app(GoalReminderService::class)->deleteReminder($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.goals.reminder', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'reminder-removed',
        ]);
    }

    // === Buddy actions (buddy-only, multiple types) ===

    /** Buddy action page — choose nudge / encouragement / offer_help (buddy only). */
    public function goalsBuddyActions(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        // Only the assigned buddy may send accountability actions (createBuddyNote
        // re-checks this server-side, but gate the page too).
        $isBuddy = (int) ($goal['mentor_id'] ?? 0) === $userId && ($goal['mentor_id'] ?? null) !== null;
        abort_unless($isBuddy, 403);

        return $this->view('accessible-frontend::goals-buddy-actions', [
            'title' => __('govuk_alpha_goals.buddy.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'buddyTypes' => self::GOALS_PARITY_BUDDY_TYPES,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** Send a buddy action with a chosen type. Notifies the owner (mirrors API). */
    public function goalsStoreBuddyAction(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();

        $type = self::asStr($request->input('type'));
        if (! in_array($type, self::GOALS_PARITY_BUDDY_TYPES, true)) {
            $type = 'encouragement';
        }
        $message = trim(self::asStr($request->input('message')));

        $note = null;
        try {
            // createBuddyNote() verifies mentor_id === buddyId internally (tenant-scoped),
            // so a non-buddy or cross-tenant attempt simply returns null.
            $note = app(GoalService::class)->createBuddyNote($id, $userId, [
                'type' => $type,
                'message' => $message !== '' ? mb_substr($message, 0, 1000) : '',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($note !== null) {
            $this->goalsParityNotifyOwnerOfBuddyAction($id, $userId, $goal, $note);
        }

        return redirect()->route('govuk-alpha.goals.buddy-actions', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $note !== null ? 'buddy-action-sent' : 'buddy-action-failed',
        ]);
    }

    /**
     * Mirror GoalsController::buddyNudge's owner notification, rendered in the
     * owner's preferred language. Best-effort; never blocks the redirect.
     *
     * @param array<string,mixed> $goal
     * @param array<string,mixed> $note
     */
    private function goalsParityNotifyOwnerOfBuddyAction(int $id, int $userId, array $goal, array $note): void
    {
        try {
            $ownerId = (int) ($goal['user_id'] ?? 0);
            if ($ownerId === 0 || $ownerId === $userId) {
                return;
            }
            $buddy = User::find($userId);
            $owner = User::find($ownerId);
            if ($owner === null) {
                return;
            }
            $goalTitle = (string) ($goal['title'] ?? '');
            $type = (string) ($note['type'] ?? 'encouragement');
            LocaleContext::withLocale($owner, function () use ($buddy, $goalTitle, $ownerId, $id, $type): void {
                $buddyName = $buddy->name ?? __('emails.common.fallback_someone');
                $body = __('api_controllers_3.goals.buddy_action_owner', [
                    'name' => $buddyName,
                    'title' => $goalTitle,
                    'action' => __('api_controllers_3.goals.buddy_action_' . $type),
                ]);
                Notification::createNotification($ownerId, $body, "/goals/{$id}", 'goal_buddy');
                \App\Services\NotificationDispatcher::fanOutPush($ownerId, 'goal_buddy', $body, "/goals/{$id}");
            });
        } catch (\Throwable $e) {
            Log::warning('Goal buddy action notification failed (accessible)', ['goal' => $id, 'error' => $e->getMessage()]);
        }
    }

    // === Social: likes + comments ===

    /**
     * Goal social page — heart-like state + threaded comments.
     *
     * Mirrors the React GoalDetailPage's <SocialInteractionPanel targetType="goal">
     * (likes via POST /v2/social/like, comments via CommentService). Only members
     * who can already see the goal (owner, buddy, or a public goal) reach it; the
     * underlying services re-check visibility server-side as defence in depth.
     */
    public function goalsSocial(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        // Same visibility gate as the goal detail page: owner, assigned buddy, or
        // any member for a public goal. Stranger-private goals are 403.
        abort_unless($this->goalsParityCanView($goal, $userId), 403);

        $comments = [];
        $likeCount = 0;
        $liked = false;
        try {
            $comments = CommentService::getForEntity('goal', $id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            [$likeCount, $liked] = $this->goalsParityLikeState($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::goals-social', [
            'title' => __('govuk_alpha_goals.social.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'goal' => $goal,
            'comments' => is_array($comments) ? $comments : [],
            'commentCount' => CommentService::countAll(is_array($comments) ? $comments : []),
            'likeCount' => $likeCount,
            'liked' => $liked,
            'currentUserId' => $userId,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Toggle the caller's heart-like on a goal.
     *
     * Mirrors SocialController::likeV2 exactly — INSERT IGNORE / DELETE on the
     * tenant-scoped likes table, visibility-checked first, and a best-effort
     * owner notification on the like (never on the unlike, never on self-like).
     */
    public function goalsToggleLike(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        abort_unless($this->goalsParityCanView($goal, $userId), 403);

        $status = 'like-failed';
        try {
            $tenantId = (int) TenantContext::getId();
            if (! FeedItemTables::canView('goal', $id, $userId)) {
                return $this->goalsSocialRedirect($tenantSlug, $id, 'like-failed');
            }

            $existing = DB::table('likes')
                ->where('user_id', $userId)
                ->where('target_type', 'goal')
                ->where('target_id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existing) {
                DB::table('likes')
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->delete();
                $status = 'unliked';
            } else {
                DB::affectingStatement(
                    'INSERT IGNORE INTO likes (user_id, target_type, target_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())',
                    [$userId, 'goal', $id, $tenantId]
                );
                $status = 'liked';
                $this->goalsParityNotifyLike($id, $userId);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->goalsSocialRedirect($tenantSlug, $id, $status);
    }

    /** Add a comment (or reply) to a goal. Notifies the goal owner. */
    public function goalsStoreComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        abort_unless($this->goalsParityCanView($goal, $userId), 403);

        $body = trim(self::asStr($request->input('body')));
        $parentRaw = self::asStr($request->input('parent_id'));
        $parentId = ctype_digit($parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if ($body === '') {
            return $this->goalsSocialRedirect($tenantSlug, $id, 'comment-invalid');
        }

        $status = 'comment-failed';
        $commentId = null;
        try {
            $result = CommentService::addComment(
                $userId,
                (int) TenantContext::getId(),
                'goal',
                $id,
                mb_substr($body, 0, 5000),
                $parentId
            );
            if (! empty($result['success'])) {
                $status = $parentId !== null ? 'reply-added' : 'comment-added';
                $commentId = isset($result['comment']['id']) ? (int) $result['comment']['id'] : null;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if (in_array($status, ['comment-added', 'reply-added'], true)) {
            $this->goalsParityNotifyComment($id, $userId, $goal, $body, $commentId);
        }

        return $this->goalsSocialRedirect($tenantSlug, $id, $status, 'comments');
    }

    /** Delete one of the caller's own comments on a goal (cascades to replies). */
    public function goalsDeleteComment(Request $request, string $tenantSlug, int $id, int $commentId): RedirectResponse
    {
        $goal = $this->goalsParityLoad($tenantSlug, $id);
        if ($goal instanceof RedirectResponse) {
            return $goal;
        }

        $userId = (int) $this->currentUserId();
        abort_unless($this->goalsParityCanView($goal, $userId), 403);

        $status = 'comment-delete-failed';
        try {
            // CommentService::delete is filtered by (id, tenant_id, user_id) so a
            // member can only ever remove their own comment.
            $status = CommentService::delete($commentId, $userId) > 0
                ? 'comment-deleted'
                : 'comment-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->goalsSocialRedirect($tenantSlug, $id, $status, 'comments');
    }

    /**
     * Like count + whether the caller has liked, from the tenant-scoped likes
     * table (same source SocialController::likeV2 returns).
     *
     * @return array{0:int,1:bool}
     */
    private function goalsParityLikeState(int $id, int $userId): array
    {
        $tenantId = (int) TenantContext::getId();
        $count = (int) DB::table('likes')
            ->where('target_type', 'goal')
            ->where('target_id', $id)
            ->where('tenant_id', $tenantId)
            ->count();
        $liked = DB::table('likes')
            ->where('user_id', $userId)
            ->where('target_type', 'goal')
            ->where('target_id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        return [$count, $liked];
    }

    /** Redirect back to the goal social page with a status flash. */
    private function goalsSocialRedirect(string $tenantSlug, int $id, string $status, ?string $fragment = null): RedirectResponse
    {
        $url = route('govuk-alpha.goals.social', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);
        if ($fragment !== null) {
            $url .= '#' . $fragment;
        }

        return redirect()->to($url);
    }

    /**
     * Mirror SocialController::likeV2's like notification. The service resolves
     * the recipient's locale itself; we only guard self-likes and exceptions.
     */
    private function goalsParityNotifyLike(int $id, int $userId): void
    {
        try {
            $ownerId = SocialNotificationService::getContentOwnerId('goal', $id);
            if ($ownerId && $ownerId !== $userId) {
                $preview = SocialNotificationService::getContentPreview('goal', $id);
                SocialNotificationService::notifyLike($ownerId, $userId, 'goal', $id, $preview);
            }
        } catch (\Throwable $e) {
            Log::warning('Goal like notification failed (accessible)', ['goal' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Mirror SocialController::addComment's owner notification. The service
     * renders the bell + email in the recipient's preferred locale internally.
     *
     * @param array<string,mixed> $goal
     */
    private function goalsParityNotifyComment(int $id, int $userId, array $goal, string $body, ?int $commentId): void
    {
        try {
            $ownerId = (int) ($goal['user_id'] ?? 0);
            if ($ownerId === 0 || $ownerId === $userId) {
                return;
            }
            SocialNotificationService::notifyComment($ownerId, $userId, 'goal', $id, $body, $commentId);
        } catch (\Throwable $e) {
            Log::warning('Goal comment notification failed (accessible)', ['goal' => $id, 'error' => $e->getMessage()]);
        }
    }
}
