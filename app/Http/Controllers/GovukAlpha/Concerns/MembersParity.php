<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Members directory & profiles — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * The core AlphaController already ships the member directory (members()),
 * the member profile page (memberProfile()), connection / endorse / block
 * actions, profile stats, reviews, availability, activity and earned-badge
 * display. Those are NOT rebuilt here.
 *
 * This trait delivers the React-parity reputation surface that the core
 * profile page does NOT yet render (and which lives in service payloads the
 * core profileStats() helper drops on the floor):
 *   - membersInsights: a "Reputation and recognition" page per member that
 *     surfaces the NEXUS score badge (total / tier / percentile), the full
 *     stats grid INCLUDING groups joined and events attended, the per-method
 *     verification badge row (email / phone / ID / address / admin / DBS /
 *     org-vouched / peer-endorsed), and the showcased earned badges grid.
 *     Parity with react-frontend/src/pages/profile/ProfilePage.tsx
 *     (NEXUS score 671-697, stat cards 854-883 incl. groups/events,
 *     VerificationBadgeSummary 1043-1048, showcased badges 758-766).
 *
 * Every backing call is the SAME tenant-scoped service the React API uses:
 *   - \App\Services\UserService::getPublicProfile() / getOwnProfile()
 *     (mirrors UsersController profile payload — includes stats, nexus_score,
 *     groups_count, events_attended, badges).
 *   - \App\Services\MemberVerificationBadgeService::getUserBadges()
 *     (mirrors the verification badge endpoint VerificationBadgeSummary reads).
 * No money/auth/notification logic is reimplemented; this is a read-only view.
 */
trait MembersParity
{
    /**
     * "Reputation and recognition" page for a member. Surfaces the NEXUS score,
     * the full activity stats grid (incl. groups joined + events attended), the
     * per-method verification badge row and the showcased earned badges — all of
     * which the React profile renders but the core accessible profile page does
     * not. Auth-gated and feature-gated identically to the core memberProfile()
     * page (connections feature), with the same cross-tenant 404 + block-aware
     * privacy semantics (delegated to UserService).
     */
    public function membersInsights(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(\App\Core\TenantContext::hasFeature('connections'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $isOwnProfile = $id === $viewerId;

        // getPublicProfile() applies block + privacy + onboarding-visibility gating
        // and returns null for a hidden / cross-tenant / unknown member (the same
        // null the core profile page 404s on). Calling it with viewerId === id for
        // the owner skips the privacy stripping and returns the full own payload.
        $profile = \App\Services\UserService::getPublicProfile($id, $viewerId);
        abort_if($profile === null, 404);

        $stats = is_array($profile['stats'] ?? null) ? $profile['stats'] : [];

        // Flattened-to-root fields (UserService::getPublicProfile flattens these)
        // with a stats[] fallback so own-profile (getMe) payloads still resolve.
        $insightsStats = [
            'hours_given'     => (float) ($profile['total_hours_given'] ?? $stats['total_hours_given'] ?? 0),
            'hours_received'  => (float) ($profile['total_hours_received'] ?? $stats['total_hours_received'] ?? 0),
            'listings_count'  => (int) ($stats['listings_count'] ?? 0),
            'connections_count' => (int) ($stats['connections_count'] ?? 0),
            'reviews_count'   => (int) ($stats['reviews_count'] ?? 0),
            'groups_count'    => (int) ($profile['groups_count'] ?? $stats['groups_count'] ?? 0),
            'events_attended' => (int) ($profile['events_attended'] ?? $stats['events_attended'] ?? 0),
            'rating'          => $profile['rating'] ?? $stats['average_rating'] ?? null,
            'level'           => (int) ($profile['level'] ?? 1),
            'xp'              => (int) ($profile['xp'] ?? 0),
        ];

        // NEXUS score summary: { total_score, tier, percentile } or null.
        $nexusScore = is_array($profile['nexus_score'] ?? null) ? $profile['nexus_score'] : null;

        // Per-method verification badges (email/phone/id/address/admin/dbs/org/peer).
        $verificationBadges = [];
        try {
            $verificationBadges = app(\App\Services\MemberVerificationBadgeService::class)->getUserBadges($id);
        } catch (\Throwable $e) {
            report($e);
        }

        // Earned / showcased badges — the gamification badges with name + icon.
        $earnedBadges = is_array($profile['badges'] ?? null) ? $profile['badges'] : [];

        $displayName = trim((string) ($profile['name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim(((string) ($profile['first_name'] ?? '')) . ' ' . ((string) ($profile['last_name'] ?? '')));
        }
        if ($displayName === '') {
            $displayName = __('govuk_alpha_members.insights.unknown_member');
        }

        return $this->view('accessible-frontend::members-insights', [
            'title'              => __('govuk_alpha_members.insights.title', ['name' => $displayName]),
            'tenantSlug'         => $tenantSlug,
            'activeNav'          => $isOwnProfile ? 'profile' : 'members',
            'memberId'           => $id,
            'isOwnProfile'       => $isOwnProfile,
            'displayName'        => $displayName,
            'profile'            => $profile,
            'insightsStats'      => $insightsStats,
            'nexusScore'         => $nexusScore,
            'verificationBadges' => $verificationBadges,
            'earnedBadges'       => $earnedBadges,
        ]);
    }
}
