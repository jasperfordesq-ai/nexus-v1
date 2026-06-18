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
 * Activity — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * The core /alpha/activity dashboard is rendered by AlphaController::activity()
 * (a single-column linear summary). This trait adds the richer React-parity
 * "Activity insights" surface (ActivityDashboardPage.tsx): activity-type
 * badges on the timeline, skill offering/requesting tags + endorsement counts,
 * a dual-bar (given vs received) monthly chart, colour/sign-aware net balance,
 * and a two-column layout with a quick-stats sidebar — all from the same
 * MemberActivityService::getDashboardData() the React API controller calls.
 */
trait ActivityParity
{
    /**
     * Activity insights — auth-only, self. Mirrors GET
     * /api/v2/users/me/activity/dashboard (MemberActivityService::getDashboardData).
     *
     * No extra feature gate: the React Activity page is protected-but-ungated
     * (see react-frontend pages table — Activity has no FeatureGate), matching
     * the existing AlphaController::activity() behaviour.
     */
    public function activityInsights(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        $data = [];
        try {
            $data = app(\App\Services\MemberActivityService::class)->getDashboardData($userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::activity-insights', [
            'title' => __('govuk_alpha_activity.insights.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'activity',
            'activity' => is_array($data) ? $data : [],
        ]);
    }
}
