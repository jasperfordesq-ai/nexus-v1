<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\CommentService;
use App\Services\ListingConfigurationService;
use App\Services\ListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Listings & exchanges — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * Closes listings parity gaps:
 *   - #12  owner-only listing analytics panel (listingsAnalytics)
 *   - social comment thread on a listing — server-rendered list + add-comment
 *     form, mirroring the React ListingDetailPage <CommentsSection> which posts
 *     to POST /v2/comments (CommentService::create, target_type=listing). The
 *     listing detail view links to this page.
 *   - AI description helper on the create/edit form — a no-JS "generate"
 *     button that round-trips through ListingsController::generateDescription's
 *     backing service (AiChatService + ListingConfigurationService gate).
 *
 * Save/unsave toggle, report link, renew + expires_at/renewal_count, the
 * active-exchange banner, and the group-exchange participant search + role
 * assignment are ALREADY present in the core listing/exchange views and the
 * group-exchange-detail page, so they are not rebuilt here.
 */
trait ListingsParity
{
    /**
     * Gap #12 — Owner-only listing analytics dashboard.
     *
     * Mirrors the React <ListingAnalyticsPanel> on ListingDetailPage: key
     * metrics (views, unique viewers, contacts, saves, contact/save rates,
     * 7-day views trend), a views-over-time and contacts-over-time accessible
     * sparkbar table, and a contact-types breakdown. Reuses the exact service
     * the React ListingsController::analytics() endpoint calls
     * (ListingAnalyticsService::getAnalytics) plus ListingService::canModify for
     * the same owner/admin gate, so no analytics logic is reimplemented.
     */
    public function listingsAnalytics(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Cross-tenant / missing listing => 404 (getById is tenant-scoped).
        $listing = \App\Services\ListingService::getById($id, false, $userId);
        abort_if($listing === null, 404);

        // Same owner/admin gate the React analytics endpoint enforces.
        abort_unless(\App\Services\ListingService::canModify($listing, $userId), 403);

        // Clamp the window exactly as the API does (days: 1..90, default 30).
        $days = (int) $this->allowed((string) $request->query('days', '30'), ['7', '14', '30', '60', '90'], '30');

        $analytics = [];
        try {
            $analytics = app(\App\Services\ListingAnalyticsService::class)->getAnalytics($id, $days);
        } catch (\Throwable $e) {
            report($e);
        }
        // getAnalytics returns ['error' => ...] when the listing row is gone.
        if (isset($analytics['error'])) {
            $analytics = [];
        }

        $listingTitle = self::asStr($listing['title'] ?? '');
        if ($listingTitle === '') {
            $listingTitle = self::asStr($analytics['title'] ?? '');
        }

        return $this->view('accessible-frontend::listings-analytics', [
            'title' => __('govuk_alpha_listings.analytics.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'listingId' => $id,
            'listingTitle' => $listingTitle !== '' ? $listingTitle : __('govuk_alpha_listings.analytics.title'),
            'analytics' => $analytics,
            'days' => $days,
        ]);
    }

    /**
     * Listing comment thread — server-rendered list + add-comment form.
     *
     * Mirrors the React ListingDetailPage <CommentsSection>: the React app loads
     * the thread from GET /v2/comments?target_type=listing&target_id={id} (which
     * calls CommentService::getForEntity). Auth is required to view the thread,
     * matching the React panel which only loads comments for signed-in members.
     */
    public function listingsComments(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        // Tenant-scoped fetch — cross-tenant / missing listing => 404.
        $listing = ListingService::getById($id, false, $userId);
        abort_if($listing === null, 404);

        $comments = [];
        try {
            // 'listing' is a commentable target_type (FeedItemTables::COMMENTABLE_TYPES).
            $comments = CommentService::getForEntity('listing', $id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::listings-comments', [
            'title' => __('govuk_alpha_listings.comments.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'listings',
            'listingId' => $id,
            'listingTitle' => self::asStr($listing['title'] ?? '') ?: __('govuk_alpha_listings.comments.title'),
            'comments' => is_array($comments) ? $comments : [],
            'commentsCount' => CommentService::countAll(is_array($comments) ? $comments : []),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Add a comment (or threaded reply via parent_id) to a listing.
     *
     * Calls the SAME service the React POST /v2/comments endpoint uses —
     * CommentService::create — so @mention processing AND the content-owner /
     * parent-author notifications (each already wrapped in LocaleContext inside
     * SocialNotificationService) fire exactly as they do for the React frontend.
     * No notification logic is reimplemented here.
     */
    public function listingsStoreComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listing = ListingService::getById($id, false, $userId);
        abort_if($listing === null, 404);

        $body = trim(self::asStr($request->input('body')));
        $parentRaw = self::asStr($request->input('parent_id'));
        $parentId = ctype_digit($parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if ($body === '') {
            return $this->listingsCommentsRedirect($tenantSlug, $id, 'comment-invalid');
        }

        $status = 'comment-failed';
        try {
            CommentService::create('listing', $id, $userId, (int) TenantContext::getId(), [
                'content' => mb_substr($body, 0, 5000),
                'parent_id' => $parentId,
            ]);
            $status = $parentId !== null ? 'reply-added' : 'comment-added';
        } catch (\InvalidArgumentException $e) {
            // Validation failures (empty after sanitising, bad parent, too deep).
            $status = 'comment-invalid';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->listingsCommentsRedirect($tenantSlug, $id, $status);
    }

    /** Redirect back to the listing comments page with a status flash + anchor. */
    private function listingsCommentsRedirect(string $tenantSlug, int $id, string $status): RedirectResponse
    {
        return redirect()
            ->route('govuk-alpha.listings.comments', ['tenantSlug' => $tenantSlug, 'id' => $id, 'status' => $status])
            ->withFragment('add-comment');
    }

    /**
     * AI description helper — no-JS round-trip used by the create + edit forms.
     *
     * Mirrors ListingsController::generateDescription: the same
     * ListingConfigurationService AI-descriptions gate and the same
     * AiChatService::chat prompt shape. The generated text is flashed back into
     * the form via withInput() so it lands in the description textarea exactly
     * as if the member had typed it — no JavaScript required. When the feature
     * is disabled or the AI provider errors, we redirect back with a status the
     * form surfaces, never discarding what the member already entered.
     */
    public function listingsGenerateDescription(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('listings'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $listingRaw = self::asStr($request->input('listing_id'));
        $listingId = ctype_digit($listingRaw) && (int) $listingRaw > 0 ? (int) $listingRaw : null;

        // Preserve everything the member already typed so a generate round-trip
        // never loses their other field values.
        $carry = [
            'type' => $this->allowed(self::asStr($request->input('type')), ['offer', 'request'], 'offer'),
            'title' => mb_substr(trim(self::asStr($request->input('title'))), 0, 255),
            'description' => mb_substr(trim(self::asStr($request->input('description'))), 0, 5000),
            'category_id' => self::asStr($request->input('category_id')),
            'hours_estimate' => self::asStr($request->input('hours_estimate')),
            'service_type' => self::asStr($request->input('service_type')),
            'location' => mb_substr(trim(self::asStr($request->input('location'))), 0, 255),
        ];

        $back = function (string $status, array $extra = []) use ($tenantSlug, $listingId, $carry): RedirectResponse {
            $route = $listingId !== null
                ? redirect()->route('govuk-alpha.listings.edit', ['tenantSlug' => $tenantSlug, 'id' => $listingId, 'status' => $status])
                : redirect()->route('govuk-alpha.listings.create', ['tenantSlug' => $tenantSlug, 'status' => $status]);

            return $route->withInput(array_merge($carry, $extra))->withFragment('description');
        };

        // Same gate as the React endpoint (config flag, defaults true).
        $aiEnabled = filter_var(
            ListingConfigurationService::get(
                ListingConfigurationService::CONFIG_ENABLE_AI_DESCRIPTIONS,
                ListingConfigurationService::DEFAULTS[ListingConfigurationService::CONFIG_ENABLE_AI_DESCRIPTIONS] ?? true
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$aiEnabled) {
            return $back('ai-disabled');
        }

        if ($carry['title'] === '') {
            return $back('ai-title-required');
        }

        $typeLabel = $carry['type'] === 'request' ? 'Service being requested' : 'Service being offered';
        $prompt = 'You are helping a member of a community timebank write a listing description. '
            . 'Timebanks are where community members exchange services for time credits (1 hour = 1 credit). '
            . "Write a friendly, clear description for this listing:\n\n"
            . "Type: {$typeLabel}\n"
            . "Title: {$carry['title']}\n"
            . ($carry['description'] !== '' ? "Additional context from the member: {$carry['description']}\n" : '')
            . "\nWrite 2-3 short paragraphs. Be warm and community-focused. "
            . 'Mention what the person will get from this exchange. Keep it under 200 words. '
            . 'Do not use markdown formatting. Do not include a title heading — just the description body.';

        $reply = null;
        try {
            $result = app(\App\Services\AiChatService::class)->chat(0, $prompt, [
                'system_prompt' => 'You are a friendly community writing assistant for a timebanking platform.',
                'max_tokens' => 512,
                'model' => 'gpt-4o-mini',
            ]);
            if (empty($result['error']) && trim(self::asStr($result['reply'] ?? '')) !== '') {
                $reply = trim(self::asStr($result['reply']));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($reply === null) {
            return $back('ai-failed');
        }

        return $back('ai-generated', ['description' => mb_substr($reply, 0, 5000)]);
    }
}
