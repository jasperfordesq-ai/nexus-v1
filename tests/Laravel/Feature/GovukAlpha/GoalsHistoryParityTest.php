<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GoalCheckinService;
use App\Services\GoalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature coverage for the accessible-frontend goals progress-history timeline.
 *
 * Mirrors GoalsParityTest's base class, traits and helpers. Every method is
 * prefixed test_goals_history_ to be globally unique across the suite.
 *
 * Route tested: GET /{tenantSlug}/accessible/goals/{id}/history
 * Name:         govuk-alpha.goals.history
 * Controller:   AlphaController::goalsHistory (via GoalsParity trait)
 * Backing service: GoalProgressService::getProgressHistory()
 */
class GoalsHistoryParityTest extends TestCase
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

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        Cache::flush();
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    /**
     * Create a goal owned by $ownerId via the real GoalService (tenant-scoped).
     */
    private function seedGoal(int $ownerId, array $overrides = []): int
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $goal = app(GoalService::class)->create($ownerId, array_merge([
            'title'             => 'History Test Goal',
            'description'       => 'Checking the history page',
            'target_value'      => 50,
            'current_value'     => 0,
            'is_public'         => true,
            'checkin_frequency' => 'weekly',
        ], $overrides));

        return (int) $goal->id;
    }

    /**
     * Record a check-in so the history table has at least one event.
     */
    private function seedCheckin(int $goalId, int $userId): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        try {
            app(GoalCheckinService::class)->create($goalId, $userId, [
                'progress_percent' => 20,
                'mood'             => 'good',
                'note'             => 'Seeded check-in for history test',
            ]);
        } catch (\Throwable $e) {
            // Best-effort — the test will still assert row visibility
        }
    }

    // ==================================================================
    //  AUTH
    // ==================================================================

    public function test_goals_history_requires_auth(): void
    {
        $owner  = $this->makeUser(['name' => 'History Anon Owner']);
        $goalId = $this->seedGoal($owner->id);

        $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history")
            ->assertRedirectContains('/accessible/login');
    }

    // ==================================================================
    //  RENDER
    // ==================================================================

    public function test_goals_history_renders_for_goal_owner_with_events(): void
    {
        $owner  = $this->authenticatedUser(['name' => 'History Owner']);
        $goalId = $this->seedGoal($owner->id);
        $this->seedCheckin($goalId, $owner->id);

        $res = $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.history.title'));
        // Should show at least the created event (recorded by GoalService::create)
        $res->assertSee(__('govuk_alpha_goals.history.type_created'));
        // Back link to goal detail
        $res->assertSee(
            route('govuk-alpha.goals.show', ['tenantSlug' => $this->testTenantSlug, 'id' => $goalId]),
            false
        );
    }

    public function test_goals_history_renders_empty_state_when_no_events(): void
    {
        // Seed a goal then delete history rows to force the empty branch.
        $owner  = $this->authenticatedUser(['name' => 'History Empty Owner']);
        $goalId = $this->seedGoal($owner->id);

        \Illuminate\Support\Facades\DB::table('goal_progress_history')
            ->where('goal_id', $goalId)
            ->delete();

        $res = $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.history.empty'));
    }

    public function test_goals_history_renders_for_buddy(): void
    {
        $owner  = $this->makeUser(['name' => 'History Buddy Owner']);
        $goalId = $this->seedGoal($owner->id);

        $buddy = $this->authenticatedUser(['name' => 'History Buddy User']);
        \Illuminate\Support\Facades\DB::table('goals')
            ->where('id', $goalId)
            ->update(['mentor_id' => $buddy->id]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.history.title'));
    }

    public function test_goals_history_renders_for_public_goal_viewer(): void
    {
        $owner   = $this->makeUser(['name' => 'History Public Owner']);
        $goalId  = $this->seedGoal($owner->id, ['is_public' => true]);

        // A third member who is neither owner nor buddy can view a public goal's history.
        $this->authenticatedUser(['name' => 'History Public Viewer']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.history.title'));
    }

    // ==================================================================
    //  ACCESS CONTROL
    // ==================================================================

    public function test_goals_history_forbidden_for_stranger_on_private_goal(): void
    {
        $owner  = $this->makeUser(['name' => 'History Private Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => false]);

        $this->authenticatedUser(['name' => 'History Stranger']);

        $this->get("/{$this->testTenantSlug}/accessible/goals/{$goalId}/history")
            ->assertStatus(403);
    }

    public function test_goals_history_404_for_missing_goal(): void
    {
        $this->authenticatedUser(['name' => 'History Missing User']);

        $this->get("/{$this->testTenantSlug}/accessible/goals/99999801/history")
            ->assertStatus(404);
    }

    public function test_goals_history_404_for_cross_tenant_goal(): void
    {
        // Insert a goal owned by a DIFFERENT tenant directly, with an explicit
        // tenant_id, so the row is guaranteed to carry the foreign tenant
        // regardless of TenantContext juggling inside the test harness. The
        // accessible goals-history route is tenant-scoped (GoalService::getById
        // applies the tenant global scope), so it must 404 — it must never leak
        // another tenant's goal.
        $otherTenantId = $this->testTenantId === 2 ? 1 : 2;

        $crossGoalId = DB::table('goals')->insertGetId([
            'tenant_id'     => $otherTenantId,
            'user_id'       => 999000 + $otherTenantId,
            'title'         => 'Cross-tenant Goal',
            'is_public'     => 1,
            'status'        => 'active',
            'current_value' => 0,
            'target_value'  => 10,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->authenticatedUser(['name' => 'Cross Tenant Viewer']);

        $this->get("/{$this->testTenantSlug}/accessible/goals/{$crossGoalId}/history")
            ->assertStatus(404);
    }
}
