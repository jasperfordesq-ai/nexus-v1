<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

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
 * Closes listings parity gap #12 (owner-only listing analytics panel). The
 * remaining audited gaps are either already present in the core AlphaController
 * listing/exchange/group-exchange views (timeline, completion + rating flow,
 * decline/confirm/cancel with reason, reciprocity, expires_at + renewal_count,
 * participant search + role assignment, split calculation) or require editing
 * those core views — owned by AlphaController, outside this module's allotment —
 * so they are deferred (see the build report).
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
}
