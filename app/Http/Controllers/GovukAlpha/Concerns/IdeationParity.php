<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Ideation — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Every method mirrors the React Ideation pages and calls the same services
 * the V2 API controllers use (IdeationChallengeService, CampaignService,
 * ChallengeOutcomeService, ChallengeTemplateService, ChallengeCategoryService,
 * IdeaMediaService, IdeaTeamConversionService). No money/auth/notification
 * logic is reimplemented here.
 */
trait IdeationParity
{
    // ================================================================
    // SHARED GUARDS / HELPERS
    // ================================================================

    /**
     * Standard guard for every ideation route: confirm the tenant slug, require
     * auth, and gate the feature. Returns the user id on success, or a redirect
     * to login when the visitor is not signed in.
     */
    private function ideationGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('ideation_challenges'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        return $userId;
    }

    /**
     * Whether the given user is an admin in the current tenant. Mirrors the
     * private isAdmin() checks used inside the ideation services so the views
     * can show/hide admin controls; the services still enforce on mutation.
     */
    private function ideationIsAdmin(int $userId): bool
    {
        $role = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', TenantContext::getId())
            ->value('role');

        return in_array($role ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
    }

    // ================================================================
    // IDEA DETAIL (high) — /ideation/{id}/ideas/{ideaId}
    // ================================================================

    /**
     * Dedicated idea detail page: full idea content, vote button, admin status
     * controls, comments, delete, convert-to-group. Mirrors IdeaDetailPage.tsx.
     */
    public function ideationIdeaDetail(Request $request, string $tenantSlug, int $challengeId, int $ideaId): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $svc = app(\App\Services\IdeationChallengeService::class);

        $idea = null;
        $challenge = null;
        $comments = [];
        $media = [];
        try {
            $idea = $svc->getIdeaById($ideaId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        // 404 when the idea is missing OR does not belong to the challenge in the URL.
        abort_if($idea === null || (int) ($idea['challenge_id'] ?? 0) !== $challengeId, 404);

        try {
            $challenge = $svc->getById($challengeId);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);

        try {
            $comments = $svc->getComments($ideaId, ['limit' => 30])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $media = app(\App\Services\IdeaMediaService::class)->getMediaForIdea($ideaId);
        } catch (\Throwable $e) {
            report($e);
        }

        $isAdmin = $this->ideationIsAdmin($userId);
        $isOwner = (int) ($idea['user_id'] ?? 0) === $userId;
        $ideaStatus = (string) ($idea['status'] ?? 'submitted');
        $challengeStatus = (string) ($challenge['status'] ?? 'draft');

        return $this->view('accessible-frontend::ideation-idea', [
            'title' => ($idea['title'] ?? '') ?: __('govuk_alpha_ideation.idea.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenge' => $challenge,
            'idea' => $idea,
            'comments' => is_array($comments) ? $comments : [],
            'media' => is_array($media) ? $media : [],
            'isAdmin' => $isAdmin,
            'isOwner' => $isOwner,
            'currentUserId' => $userId,
            'canVote' => in_array($challengeStatus, ['open', 'voting'], true) && ! $isOwner
                && ! in_array($ideaStatus, ['withdrawn', 'draft'], true),
            'canComment' => ! in_array($ideaStatus, ['withdrawn', 'draft'], true),
            'canConvert' => ($isAdmin || $isOwner) && in_array($ideaStatus, ['shortlisted', 'winner'], true),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Add a comment to an idea (POST). Mirrors IdeaDetailPage comment form.
     */
    public function ideationStoreComment(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $body = trim(self::asStr($request->input('comment_body')));
        $status = 'comment-failed';
        if ($body === '') {
            $status = 'comment-invalid';
        } else {
            try {
                $commentId = app(\App\Services\IdeationChallengeService::class)
                    ->addComment($ideaId, $userId, mb_substr($body, 0, 5000));
                $status = $commentId !== null ? 'comment-added' : 'comment-failed';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => $status,
        ])->withFragment('comments');
    }

    /**
     * Delete a comment (owner or admin). Mirrors IdeaDetailPage delete-comment.
     */
    public function ideationDeleteComment(Request $request, string $tenantSlug, int $challengeId, int $ideaId, int $commentId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->deleteComment($commentId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => $ok ? 'comment-deleted' : 'comment-failed',
        ])->withFragment('comments');
    }

    /**
     * Vote / unvote on an idea from the idea detail page (toggle).
     */
    public function ideationIdeaVote(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->voteIdea($ideaId, $userId) !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => $ok ? 'idea-voted' : 'idea-failed',
        ]);
    }

    /**
     * Admin: set an idea's status (shortlist / winner / clear). Mirrors the
     * IdeaDetailPage admin dropdown. Service enforces admin-only.
     */
    public function ideationIdeaStatus(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $status = $this->allowed(
            self::asStr($request->input('idea_status')),
            ['submitted', 'shortlisted', 'winner', 'withdrawn'],
            ''
        );

        $ok = false;
        if ($status !== '') {
            try {
                $ok = app(\App\Services\IdeationChallengeService::class)
                    ->updateIdeaStatus($ideaId, $userId, $status);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => $ok ? 'idea-status-updated' : 'idea-failed',
        ]);
    }

    /**
     * Delete an idea (owner or admin). Mirrors IdeaDetailPage delete-idea.
     */
    public function ideationDeleteIdea(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->deleteIdea($ideaId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            return redirect()->route('govuk-alpha.ideation.show', [
                'tenantSlug' => $tenantSlug,
                'id' => $challengeId,
                'status' => 'idea-deleted',
            ])->withFragment('ideas');
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => 'idea-failed',
        ]);
    }

    /**
     * Add media (image/video/document/link) to an idea via URL. Mirrors the
     * submit-idea media attachment section. Service enforces owner/admin.
     */
    public function ideationAddMedia(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $url = trim(self::asStr($request->input('media_url')));
        $mediaType = $this->allowed(
            self::asStr($request->input('media_type')),
            ['image', 'video', 'document', 'link'],
            'image'
        );
        $caption = trim(self::asStr($request->input('media_caption')));

        $status = 'media-failed';
        if ($url === '') {
            $status = 'media-invalid';
        } else {
            try {
                $mediaId = app(\App\Services\IdeaMediaService::class)->addMedia($ideaId, $userId, [
                    'url' => mb_substr($url, 0, 1000),
                    'media_type' => $mediaType,
                    'caption' => $caption !== '' ? mb_substr($caption, 0, 500) : null,
                ]);
                $status = $mediaId !== null ? 'media-added' : 'media-failed';
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => $status,
        ])->withFragment('media');
    }

    /**
     * Convert a shortlisted/winner idea into a group (team). Mirrors the
     * IdeaDetailPage convert modal. Service enforces author/owner/admin.
     */
    public function ideationConvertToGroup(Request $request, string $tenantSlug, int $challengeId, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $name = trim(self::asStr($request->input('group_name')));
        $description = trim(self::asStr($request->input('group_description')));
        $visibility = $this->allowed(
            self::asStr($request->input('group_visibility')),
            ['public', 'private', 'secret'],
            'public'
        );

        $result = null;
        try {
            $result = app(\App\Services\IdeaTeamConversionService::class)->convert($ideaId, $userId, [
                'name' => $name !== '' ? mb_substr($name, 0, 255) : null,
                'description' => $description !== '' ? mb_substr($description, 0, 5000) : null,
                'visibility' => $visibility,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if (is_array($result) && ! empty($result['id']) && \Illuminate\Support\Facades\Route::has('govuk-alpha.groups.show')) {
            return redirect()->route('govuk-alpha.groups.show', [
                'tenantSlug' => $tenantSlug,
                'id' => (int) $result['id'],
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.idea', [
            'tenantSlug' => $tenantSlug,
            'id' => $challengeId,
            'ideaId' => $ideaId,
            'status' => is_array($result) ? 'converted' : 'convert-failed',
        ]);
    }

    // ================================================================
    // CHALLENGE CREATE / EDIT (high) — admin only
    // ================================================================

    /**
     * Render the create-challenge form (admin only). Mirrors CreateChallengePage.
     */
    public function ideationCreateChallenge(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        return $this->view('accessible-frontend::ideation-challenge-form', [
            'title' => __('govuk_alpha_ideation.form.create_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'mode' => 'create',
            'challenge' => null,
            'categories' => $this->ideationCategories(),
            'templates' => $this->ideationTemplates(),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Persist a new challenge (admin only). Mirrors POST /v2/ideation-challenges.
     */
    public function ideationStoreChallenge(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $payload = $this->ideationChallengePayload($request);
        if (trim((string) $payload['title']) === '' || trim((string) $payload['description']) === '') {
            return redirect()->route('govuk-alpha.ideation.create', [
                'tenantSlug' => $tenantSlug,
                'status' => 'challenge-invalid',
            ]);
        }

        $newId = null;
        try {
            $newId = app(\App\Services\IdeationChallengeService::class)->create($userId, $payload);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newId) {
            return redirect()->route('govuk-alpha.ideation.show', [
                'tenantSlug' => $tenantSlug,
                'id' => (int) $newId,
                'status' => 'challenge-created',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.create', [
            'tenantSlug' => $tenantSlug,
            'status' => 'challenge-failed',
        ]);
    }

    /**
     * Render the edit-challenge form (admin only). Cross-tenant unknown id → 404.
     */
    public function ideationEditChallenge(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $challenge = null;
        try {
            $challenge = app(\App\Services\IdeationChallengeService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);

        return $this->view('accessible-frontend::ideation-challenge-form', [
            'title' => __('govuk_alpha_ideation.form.edit_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'mode' => 'edit',
            'challenge' => $challenge,
            'categories' => $this->ideationCategories(),
            'templates' => [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Persist a challenge edit (admin only). Mirrors PUT /v2/ideation-challenges/{id}.
     */
    public function ideationUpdateChallenge(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $exists = DB::table('ideation_challenges')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::getId())
            ->exists();
        abort_unless($exists, 404);

        $payload = $this->ideationChallengePayload($request);
        if (trim((string) $payload['title']) === '' || trim((string) $payload['description']) === '') {
            return redirect()->route('govuk-alpha.ideation.edit', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'challenge-invalid',
            ]);
        }

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->updateChallenge($id, $userId, $payload);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'challenge-updated' : 'challenge-failed',
        ]);
    }

    /**
     * Build the create/update challenge payload from the request.
     *
     * @return array<string,mixed>
     */
    private function ideationChallengePayload(Request $request): array
    {
        $rawTags = self::asStr($request->input('tags'));
        $tags = array_values(array_filter(array_map('trim', explode(',', $rawTags))));

        $status = $this->allowed(
            self::asStr($request->input('challenge_status')),
            ['draft', 'open', 'voting', 'evaluating', 'closed', 'archived'],
            'draft'
        );

        $categoryId = self::asStr($request->input('category_id'));
        $maxIdeas = self::asStr($request->input('max_ideas_per_user'));

        return [
            'title' => mb_substr(trim(self::asStr($request->input('title'))), 0, 255),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 5000),
            'category' => mb_substr(trim(self::asStr($request->input('category'))), 0, 100),
            'prize_description' => mb_substr(trim(self::asStr($request->input('prize_description'))), 0, 2000),
            'cover_image' => mb_substr(trim(self::asStr($request->input('cover_image'))), 0, 500),
            'submission_deadline' => trim(self::asStr($request->input('submission_deadline'))) ?: null,
            'voting_deadline' => trim(self::asStr($request->input('voting_deadline'))) ?: null,
            'max_ideas_per_user' => $maxIdeas !== '' ? (int) $maxIdeas : null,
            'category_id' => ctype_digit($categoryId) ? (int) $categoryId : null,
            'tags' => $tags,
            'status' => $status,
        ];
    }

    // ================================================================
    // CHALLENGE MANAGE (admin) — lifecycle / duplicate / delete / link hub
    // ================================================================

    /**
     * Admin management hub for a single challenge: valid status transitions,
     * favourite toggle, edit/outcome links, duplicate, delete, link-to-campaign.
     * Consolidates the admin actions the React ChallengeDetailPage dropdown
     * exposes. Cross-tenant / unknown id → 404; non-admin → 403.
     */
    public function ideationManageChallenge(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $challenge = null;
        try {
            $challenge = app(\App\Services\IdeationChallengeService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);

        // Valid forward transitions for the current status (mirrors the service rules).
        $transitionMap = [
            'draft' => ['open'],
            'open' => ['voting', 'evaluating', 'closed'],
            'voting' => ['evaluating', 'closed'],
            'evaluating' => ['closed'],
            'closed' => ['open', 'archived'],
            'archived' => ['closed'],
        ];
        $currentStatus = (string) ($challenge['status'] ?? 'draft');
        $transitions = $transitionMap[$currentStatus] ?? [];

        $campaigns = [];
        try {
            $campaigns = app(\App\Services\CampaignService::class)->getAll(['limit' => 100])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        $isFavorited = DB::table('challenge_favorites')
            ->where('challenge_id', $id)
            ->where('user_id', $userId)
            ->exists();

        return $this->view('accessible-frontend::ideation-manage', [
            'title' => __('govuk_alpha_ideation.manage.heading'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenge' => $challenge,
            'transitions' => $transitions,
            'campaigns' => is_array($campaigns) ? $campaigns : [],
            'isFavorited' => $isFavorited,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // ================================================================
    // CHALLENGE LIFECYCLE / FAVORITE / DUPLICATE / DELETE
    // ================================================================

    /**
     * Admin: transition a challenge's status (draft→open, open→voting, etc.).
     * Mirrors PUT /v2/ideation-challenges/{id}/status. Service enforces rules.
     */
    public function ideationChallengeStatus(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $status = $this->allowed(
            self::asStr($request->input('challenge_status')),
            ['draft', 'open', 'voting', 'evaluating', 'closed', 'archived'],
            ''
        );

        $ok = false;
        if ($status !== '') {
            try {
                $ok = app(\App\Services\IdeationChallengeService::class)
                    ->updateChallengeStatus($id, $userId, $status);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'challenge-status-updated' : 'challenge-status-failed',
        ]);
    }

    /**
     * Toggle a challenge favorite. Mirrors POST /v2/ideation-challenges/{id}/favorite.
     */
    public function ideationToggleFavorite(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $favorited = false;
        try {
            $result = app(\App\Services\IdeationChallengeService::class)->toggleFavorite($id, $userId);
            $favorited = (bool) ($result['favorited'] ?? false);
        } catch (\Throwable $e) {
            report($e);
        }

        $redirectTo = self::asStr($request->input('redirect_to'));
        if ($redirectTo === 'list') {
            return redirect()->route('govuk-alpha.ideation.index', [
                'tenantSlug' => $tenantSlug,
                'status' => $favorited ? 'favorited' : 'unfavorited',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $favorited ? 'favorited' : 'unfavorited',
        ]);
    }

    /**
     * Admin: duplicate a challenge as a draft copy, then open its edit page.
     * Mirrors POST /v2/ideation-challenges/{id}/duplicate.
     */
    public function ideationDuplicateChallenge(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $newId = null;
        try {
            $newId = app(\App\Services\IdeationChallengeService::class)->duplicateChallenge($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newId) {
            return redirect()->route('govuk-alpha.ideation.edit', [
                'tenantSlug' => $tenantSlug,
                'id' => (int) $newId,
                'status' => 'challenge-duplicated',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'challenge-failed',
        ]);
    }

    /**
     * Admin: delete a challenge. Mirrors DELETE /v2/ideation-challenges/{id}.
     */
    public function ideationDeleteChallenge(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->deleteChallenge($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            return redirect()->route('govuk-alpha.ideation.index', [
                'tenantSlug' => $tenantSlug,
                'status' => 'challenge-deleted',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'challenge-failed',
        ]);
    }

    /**
     * Admin: link a challenge to a campaign. Mirrors POST
     * /v2/ideation-campaigns/{campaignId}/challenges.
     */
    public function ideationLinkCampaign(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $campaignId = self::asStr($request->input('campaign_id'));
        $ok = false;
        if (ctype_digit($campaignId)) {
            try {
                $ok = app(\App\Services\CampaignService::class)
                    ->linkChallenge((int) $campaignId, $id, $userId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('govuk-alpha.ideation.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'campaign-linked' : 'campaign-link-failed',
        ]);
    }

    // ================================================================
    // CAMPAIGNS (high) — list / detail / create / edit / delete / unlink
    // ================================================================

    /**
     * Campaign list with create form (admin). Mirrors CampaignsPage.tsx.
     */
    public function ideationCampaigns(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $items = [];
        try {
            $items = app(\App\Services\CampaignService::class)->getAll(['limit' => 50])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation-campaigns', [
            'title' => __('govuk_alpha_ideation.campaigns.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'campaigns' => is_array($items) ? $items : [],
            'isAdmin' => $this->ideationIsAdmin($userId),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Campaign detail with linked challenges (admin can unlink). Mirrors
     * CampaignDetailPage.tsx. Cross-tenant / unknown id → 404.
     */
    public function ideationCampaignDetail(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $campaign = null;
        try {
            $campaign = app(\App\Services\CampaignService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($campaign === null, 404);

        return $this->view('accessible-frontend::ideation-campaign-detail', [
            'title' => ($campaign['title'] ?? '') ?: __('govuk_alpha_ideation.campaigns.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'campaign' => $campaign,
            'isAdmin' => $this->ideationIsAdmin($userId),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Create a campaign (admin only). Mirrors POST /v2/ideation-campaigns.
     */
    public function ideationStoreCampaign(Request $request, string $tenantSlug): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $payload = $this->ideationCampaignPayload($request);
        if (trim((string) $payload['title']) === '') {
            return redirect()->route('govuk-alpha.ideation.campaigns', [
                'tenantSlug' => $tenantSlug,
                'status' => 'campaign-invalid',
            ])->withFragment('create');
        }

        $newId = null;
        try {
            $newId = app(\App\Services\CampaignService::class)->create($userId, $payload);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($newId) {
            return redirect()->route('govuk-alpha.ideation.campaign', [
                'tenantSlug' => $tenantSlug,
                'id' => (int) $newId,
                'status' => 'campaign-created',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.campaigns', [
            'tenantSlug' => $tenantSlug,
            'status' => 'campaign-failed',
        ])->withFragment('create');
    }

    /**
     * Update a campaign (admin only). Mirrors PUT /v2/ideation-campaigns/{id}.
     */
    public function ideationUpdateCampaign(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        // Confirm the campaign exists in this tenant before mutating (404 otherwise).
        $existing = null;
        try {
            $existing = app(\App\Services\CampaignService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($existing === null, 404);

        $payload = $this->ideationCampaignPayload($request);
        if (trim((string) $payload['title']) === '') {
            return redirect()->route('govuk-alpha.ideation.campaign', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'campaign-invalid',
            ])->withFragment('edit');
        }

        $ok = false;
        try {
            $ok = app(\App\Services\CampaignService::class)->update($id, $userId, $payload);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.campaign', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'campaign-updated' : 'campaign-failed',
        ]);
    }

    /**
     * Delete a campaign (admin only). Mirrors DELETE /v2/ideation-campaigns/{id}.
     */
    public function ideationDeleteCampaign(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $ok = false;
        try {
            $ok = app(\App\Services\CampaignService::class)->delete($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            return redirect()->route('govuk-alpha.ideation.campaigns', [
                'tenantSlug' => $tenantSlug,
                'status' => 'campaign-deleted',
            ]);
        }

        return redirect()->route('govuk-alpha.ideation.campaign', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'campaign-failed',
        ]);
    }

    /**
     * Admin: unlink a challenge from a campaign. Mirrors DELETE
     * /v2/ideation-campaigns/{id}/challenges/{challengeId}.
     */
    public function ideationUnlinkCampaignChallenge(Request $request, string $tenantSlug, int $id, int $challengeId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $ok = false;
        try {
            $ok = app(\App\Services\CampaignService::class)->unlinkChallenge($id, $challengeId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.campaign', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'challenge-unlinked' : 'campaign-failed',
        ])->withFragment('challenges');
    }

    /**
     * Build the create/update campaign payload from the request.
     *
     * @return array<string,mixed>
     */
    private function ideationCampaignPayload(Request $request): array
    {
        $status = $this->allowed(
            self::asStr($request->input('campaign_status')),
            ['draft', 'active', 'completed', 'archived'],
            'draft'
        );

        return [
            'title' => mb_substr(trim(self::asStr($request->input('title'))), 0, 255),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 5000),
            'cover_image' => mb_substr(trim(self::asStr($request->input('cover_image'))), 0, 500),
            'start_date' => trim(self::asStr($request->input('start_date'))) ?: null,
            'end_date' => trim(self::asStr($request->input('end_date'))) ?: null,
            'status' => $status,
        ];
    }

    // ================================================================
    // OUTCOMES (high/med) — dashboard + per-challenge outcome editing
    // ================================================================

    /**
     * Outcomes dashboard: aggregate stats + outcome list. Mirrors
     * OutcomesDashboardPage.tsx.
     */
    public function ideationOutcomes(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $dashboard = ['outcomes' => [], 'stats' => [
            'total' => 0, 'implemented' => 0, 'in_progress' => 0, 'not_started' => 0, 'abandoned' => 0,
        ]];
        try {
            $dashboard = app(\App\Services\ChallengeOutcomeService::class)->getDashboard();
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation-outcomes', [
            'title' => __('govuk_alpha_ideation.outcomes.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'outcomes' => is_array($dashboard['outcomes'] ?? null) ? $dashboard['outcomes'] : [],
            'stats' => is_array($dashboard['stats'] ?? null) ? $dashboard['stats'] : [],
        ]);
    }

    /**
     * Per-challenge outcome editor (admin). Mirrors the ChallengeDetailPage
     * outcome modal. Cross-tenant / unknown id → 404.
     */
    public function ideationOutcomeEdit(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $svc = app(\App\Services\IdeationChallengeService::class);
        $challenge = null;
        $ideas = [];
        $outcome = null;
        try {
            $challenge = $svc->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);

        try {
            $ideas = $svc->getIdeas($id, ['limit' => 100, 'sort' => 'votes'])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $outcome = app(\App\Services\ChallengeOutcomeService::class)->getForChallenge($id);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation-outcome-form', [
            'title' => __('govuk_alpha_ideation.outcomes.edit_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenge' => $challenge,
            'ideas' => is_array($ideas) ? $ideas : [],
            'outcome' => $outcome,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Persist a challenge outcome (admin). Mirrors PUT
     * /v2/ideation-challenges/{id}/outcome.
     */
    public function ideationStoreOutcome(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        abort_unless($this->ideationIsAdmin($userId), 403);

        $statusVal = $this->allowed(
            self::asStr($request->input('outcome_status')),
            ['not_started', 'in_progress', 'implemented', 'abandoned'],
            'not_started'
        );
        $winningIdea = self::asStr($request->input('winning_idea_id'));
        $impact = trim(self::asStr($request->input('impact_description')));

        $ok = false;
        try {
            $ok = app(\App\Services\ChallengeOutcomeService::class)->upsert($id, $userId, [
                'status' => $statusVal,
                'winning_idea_id' => ctype_digit($winningIdea) ? (int) $winningIdea : null,
                'impact_description' => $impact !== '' ? mb_substr($impact, 0, 5000) : null,
            ]) !== null;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('govuk-alpha.ideation.outcome', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'outcome-saved' : 'outcome-failed',
        ]);
    }

    // ================================================================
    // CATEGORY / TEMPLATE LOOKUPS
    // ================================================================

    /**
     * Categories for the current tenant (for filter + form dropdowns).
     *
     * @return list<array<string,mixed>>
     */
    private function ideationCategories(): array
    {
        try {
            $cats = app(\App\Services\ChallengeCategoryService::class)->getAll();
            return is_array($cats) ? array_values($cats) : [];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    /**
     * Challenge templates for the current tenant (for the create form picker).
     *
     * @return list<array<string,mixed>>
     */
    private function ideationTemplates(): array
    {
        try {
            $templates = app(\App\Services\ChallengeTemplateService::class)->getAll();
            return is_array($templates) ? array_values($templates) : [];
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    // ================================================================
    // DRAFT IDEAS (high) — /ideation/{id}/drafts
    // ================================================================

    /**
     * The signed-in member's draft ideas for a challenge, with an inline edit
     * + publish form per draft. Mirrors the "Your drafts" panel inside the
     * React ChallengeDetailPage submit-idea modal (GET
     * /v2/ideation-challenges/{id}/ideas/drafts). Cross-tenant / unknown
     * challenge → 404.
     *
     * Note on parity scope: the React "save a NEW draft" button posts to
     * POST /v2/ideation-challenges/{id}/ideas with is_draft=true, but the
     * shared IdeationChallengeService::submitIdea() ignores is_draft and always
     * persists status='submitted'. There is no service method that creates a
     * status='draft' idea, so new-draft creation is intentionally NOT offered
     * here (no working backend). Listing, editing and publishing existing
     * drafts ARE fully backed (getUserDrafts + updateDraftIdea) and are built.
     */
    public function ideationDrafts(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $svc = app(\App\Services\IdeationChallengeService::class);

        $challenge = null;
        try {
            $challenge = $svc->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($challenge === null, 404);

        $drafts = [];
        try {
            $drafts = $svc->getUserDrafts($id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::ideation-drafts', [
            'title' => __('govuk_alpha_ideation.drafts.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'challenge' => $challenge,
            'drafts' => is_array($drafts) ? $drafts : [],
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Save or publish an existing draft idea (owner only). Mirrors
     * PUT /v2/ideation-ideas/{ideaId}/draft. The service enforces owner +
     * draft-status; a "publish" intent promotes the draft to a submitted idea.
     */
    public function ideationUpdateDraftIdea(Request $request, string $tenantSlug, int $id, int $ideaId): RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $title = trim(self::asStr($request->input('draft_title')));
        $description = trim(self::asStr($request->input('draft_description')));
        $publish = self::asStr($request->input('draft_action')) === 'publish';

        if ($title === '') {
            return redirect()->route('govuk-alpha.ideation.drafts', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'draft-invalid',
            ]);
        }

        $ok = false;
        try {
            $ok = app(\App\Services\IdeationChallengeService::class)->updateDraftIdea($ideaId, $userId, [
                'title' => mb_substr($title, 0, 255),
                'description' => mb_substr($description, 0, 5000),
                'publish' => $publish,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // A successful publish moves the idea out of the drafts list — send the
        // member to the published idea instead of back to the (now shorter) list.
        if ($ok && $publish) {
            return redirect()->route('govuk-alpha.ideation.idea', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'ideaId' => $ideaId,
                'status' => 'idea-submitted',
            ]);
        }

        $statusKey = $ok ? 'draft-saved' : 'draft-failed';

        return redirect()->route('govuk-alpha.ideation.drafts', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $statusKey,
        ]);
    }

    // ================================================================
    // POPULAR TAGS (med) — /ideation/tags
    // ================================================================

    /**
     * Browse challenges by popular tag. Lists the most-used tags for the tenant
     * (IdeationChallengeService::getAllTags, the same data behind React's
     * GET /v2/ideation-tags/popular tag filter on IdeationPage) and, when a
     * ?tag= is selected, the challenges carrying that tag. The shared getAll()
     * does not accept a tag filter, so the selected-tag matching is done here
     * against the decoded per-challenge tags array the service already returns.
     */
    public function ideationPopularTags(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $userId = $this->ideationGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $svc = app(\App\Services\IdeationChallengeService::class);

        $tags = [];
        try {
            $tags = $svc->getAllTags();
        } catch (\Throwable $e) {
            report($e);
        }
        $tags = is_array($tags) ? $tags : [];

        $selectedTag = trim(self::asStr($request->query('tag')));
        if (mb_strlen($selectedTag) > 100) {
            $selectedTag = mb_substr($selectedTag, 0, 100);
        }

        $matches = [];
        if ($selectedTag !== '') {
            $needle = mb_strtolower($selectedTag);
            try {
                $items = $svc->getAll(['limit' => 100])['items'] ?? [];
            } catch (\Throwable $e) {
                report($e);
                $items = [];
            }
            foreach (is_array($items) ? $items : [] as $item) {
                $itemTags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
                foreach ($itemTags as $itemTag) {
                    if (mb_strtolower(trim((string) $itemTag)) === $needle) {
                        $matches[] = $item;
                        break;
                    }
                }
            }
        }

        return $this->view('accessible-frontend::ideation-tags', [
            'title' => __('govuk_alpha_ideation.tags.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'tags' => $tags,
            'selectedTag' => $selectedTag !== '' ? $selectedTag : null,
            'matches' => $matches,
        ]);
    }
}
