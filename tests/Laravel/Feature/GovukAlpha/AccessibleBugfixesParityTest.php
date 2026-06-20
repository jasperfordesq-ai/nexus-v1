<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression tests for three accessible (GOV.UK) frontend bugs:
 *
 *   1. Identity verification — the green "Verified" trust tag falsely showed for
 *      every EMAIL-verified member (`users.is_verified`). It must key on the real
 *      identity signal: an active `id_verified` verification badge (mirrors React's
 *      VerificationBadgeRow).
 *   2. Module gating — when an admin disables the `notifications` / `dashboard`
 *      module, the accessible links must disappear AND the pages must 403 (parity
 *      with React's <FeatureGate module="…">).
 *   3. Pending-reviews banner — the dashboard "exchanges to review" prompt counts
 *      completed TRANSACTIONS awaiting a review, so it must link to the Reviews
 *      page's Pending section, NOT the Exchanges list filtered to completed
 *      exchange_requests (a different table → "No results found").
 *
 * Auth/tenant scrubbing mirrors GovukAlphaFrontendTest::setUp().
 */
class AccessibleBugfixesParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        $this->resetTenant();
    }

    private function resetTenant(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
            'is_verified' => true, // EMAIL-verified — must NOT trigger the trust tag
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function grantIdVerifiedBadge(int $userId): void
    {
        DB::table('member_verification_badges')->insert([
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'badge_type' => 'id_verified',
            'verified_by' => $userId,
            'verification_note' => null,
            'expires_at' => null,
            'granted_at' => now(),
        ]);
    }

    /** Set a single module flag in the tenant's configuration JSON and re-resolve. */
    private function setModule(string $module, bool $enabled): void
    {
        $raw = DB::table('tenants')->where('id', $this->testTenantId)->value('configuration');
        $config = $raw ? (json_decode((string) $raw, true) ?: []) : [];
        $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
        $modules[$module] = $enabled;
        $config['modules'] = $modules;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['configuration' => json_encode($config)]);
        $this->resetTenant();
    }

    /** Set a single feature flag in the tenant's features JSON and re-resolve. */
    private function setFeature(string $feature, bool $enabled): void
    {
        $raw = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $features = $raw ? (json_decode((string) $raw, true) ?: []) : [];
        $features[$feature] = $enabled;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($features)]);
        $this->resetTenant();
    }

    private function seedCompletedPeerTransaction(int $reviewerId, int $counterpartyId): void
    {
        DB::table('transactions')->insert([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $counterpartyId,
            'receiver_id' => $reviewerId,
            'amount' => 5,
            'description' => 'Completed exchange awaiting review',
            'status' => 'completed',
            'transaction_type' => 'exchange',
            'deleted_for_sender' => 0,
            'deleted_for_receiver' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Bug 1: identity verification ─────────────────────────────────────────

    public function test_profile_hides_verified_tag_for_email_only_member(): void
    {
        $user = $this->authenticatedUser(); // is_verified = true, no id badge

        $response = $this->get("/{$this->testTenantSlug}/alpha/profile");

        $response->assertOk();
        // The email-verified column must NOT light up the green identity tag.
        $response->assertDontSee('govuk-tag govuk-tag--green', false);
    }

    public function test_profile_shows_verified_tag_only_with_id_verified_badge(): void
    {
        $user = $this->authenticatedUser();
        $this->grantIdVerifiedBadge($user->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/profile");

        $response->assertOk();
        $response->assertSee('govuk-tag govuk-tag--green', false);
        $response->assertSee(__('govuk_alpha.profile.verified'));
    }

    // ── Bug 2: module gating (notifications + dashboard) ─────────────────────

    public function test_notifications_page_ok_when_module_enabled(): void
    {
        $this->authenticatedUser();
        $this->setModule('notifications', true);

        $this->get("/{$this->testTenantSlug}/alpha/notifications")->assertOk();
    }

    public function test_notifications_page_403_when_module_disabled(): void
    {
        $this->authenticatedUser();
        $this->setModule('notifications', false);

        $this->get("/{$this->testTenantSlug}/alpha/notifications")->assertStatus(403);
    }

    public function test_account_hub_hides_notifications_link_when_module_disabled(): void
    {
        $this->authenticatedUser();
        $this->setModule('notifications', false);

        $response = $this->get("/{$this->testTenantSlug}/alpha/account");
        $response->assertOk();
        $response->assertDontSee(route('govuk-alpha.notifications.index', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_dashboard_page_403_when_module_disabled(): void
    {
        $this->authenticatedUser();
        $this->setModule('dashboard', false);

        $this->get("/{$this->testTenantSlug}/alpha/dashboard")->assertStatus(403);
    }

    // ── Bug 3: pending-reviews banner points at Reviews, not Exchanges ───────

    public function test_dashboard_pending_review_banner_links_to_reviews_not_exchanges(): void
    {
        $reviewer = $this->authenticatedUser();
        $counterparty = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        // A completed peer transaction the reviewer has not yet reviewed → exactly
        // what ReviewService::getPendingReviews() (and the banner count) reports.
        $this->seedCompletedPeerTransaction($reviewer->id, $counterparty->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");
        $response->assertOk();

        // Banner links to the Reviews page Pending section (same data source as the count)…
        $reviewsHref = route('govuk-alpha.reviews.index', ['tenantSlug' => $this->testTenantSlug]) . '#pending-heading';
        $response->assertSee($reviewsHref, false);
        // …and NOT the old, wrong Exchanges "completed" tab (different table).
        $response->assertDontSee('status_filter=completed', false);
    }

    public function test_dashboard_pending_review_count_is_accurate_not_capped_at_one(): void
    {
        $reviewer = $this->authenticatedUser();
        $a = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $b = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        // Two distinct completed peer transactions awaiting review.
        $this->seedCompletedPeerTransaction($reviewer->id, $a->id);
        $this->seedCompletedPeerTransaction($reviewer->id, $b->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/dashboard");
        $response->assertOk();

        // The count must reflect BOTH (was previously hard-capped at 1 by limit=1),
        // and trans_choice must pick the plural form — matching the view's render.
        $response->assertSee(trans_choice('govuk_alpha.dashboard.pending_reviews_body', 2, ['count' => 2]));
        $response->assertDontSee(trans_choice('govuk_alpha.dashboard.pending_reviews_body', 1, ['count' => 1]));
    }

    public function test_account_hub_hides_gamification_links_when_feature_disabled(): void
    {
        $this->authenticatedUser();
        $this->setFeature('gamification', false);

        $response = $this->get("/{$this->testTenantSlug}/alpha/account");
        $response->assertOk();
        $response->assertDontSee(route('govuk-alpha.achievements', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertDontSee(route('govuk-alpha.leaderboard', ['tenantSlug' => $this->testTenantSlug]), false);
        $response->assertDontSee(route('govuk-alpha.nexus-score', ['tenantSlug' => $this->testTenantSlug]), false);
    }
}
