<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GoalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature coverage for the accessible-frontend goals parity module
 * (insights panel, check-in logging with mood, reminder settings, and
 * multi-type buddy actions).
 *
 * Mirrors GovukAlphaFrontendTest's base class, traits and helpers. Every test
 * method is prefixed test_goals_ and globally unique.
 */
class GoalsParityTest extends TestCase
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

        \Illuminate\Support\Facades\Cache::flush();
    }

    // ==================================================================
    //  Helpers
    // ==================================================================

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    /**
     * Create a goal owned by $ownerId via the real service (tenant-scoped).
     */
    private function seedGoal(int $ownerId, array $overrides = []): int
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $goal = app(GoalService::class)->create($ownerId, array_merge([
            'title' => 'Walk 100 km',
            'description' => 'Quarterly walking goal',
            'target_value' => 100,
            'current_value' => 20,
            'is_public' => true,
            'checkin_frequency' => 'weekly',
        ], $overrides));

        return (int) $goal->id;
    }

    /** Assign $buddyId as the goal's mentor (buddy). */
    private function assignBuddy(int $goalId, int $buddyId): void
    {
        DB::table('goals')->where('id', $goalId)->update(['mentor_id' => $buddyId]);
    }

    // ==================================================================
    //  INSIGHTS
    // ==================================================================

    public function test_goals_insights_requires_auth(): void
    {
        $owner = $this->makeUser(['name' => 'Insights Owner Anon']);
        $goalId = $this->seedGoal($owner->id);

        $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/insights")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_goals_insights_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Insights Owner']);
        $goalId = $this->seedGoal($owner->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/insights");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.insights.title'));
        $res->assertSee(__('govuk_alpha_goals.insights.current_streak'));
        $res->assertSee(__('govuk_alpha_goals.insights.milestone_plan'));
    }

    public function test_goals_insights_forbidden_for_non_owner_private_goal(): void
    {
        $owner = $this->makeUser(['name' => 'Private Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => false]);

        $this->authenticatedUser(['name' => 'Insights Stranger']);
        $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/insights")
            ->assertStatus(403);
    }

    public function test_goals_insights_404_for_unknown_goal(): void
    {
        $this->authenticatedUser(['name' => 'Insights Missing']);
        $this->get("/{$this->testTenantSlug}/alpha/goals/99999001/insights")
            ->assertStatus(404);
    }

    // ==================================================================
    //  CHECK-IN
    // ==================================================================

    public function test_goals_checkin_form_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Checkin Owner']);
        $goalId = $this->seedGoal($owner->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/checkin");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.checkin.title'));
        $res->assertSee(__('govuk_alpha_goals.checkin.mood_legend'));
        $res->assertSee('name="mood"', false);
    }

    public function test_goals_checkin_form_forbidden_for_non_owner(): void
    {
        $owner = $this->makeUser(['name' => 'Checkin Real Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        // A public goal is viewable, but only the owner may log a check-in.
        $this->authenticatedUser(['name' => 'Checkin Stranger']);
        $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/checkin")
            ->assertStatus(403);
    }

    public function test_goals_store_checkin_persists_and_updates_progress(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Checkin Saver']);
        $goalId = $this->seedGoal($owner->id, ['target_value' => 100, 'current_value' => 0]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/checkin", [
            'progress_percent' => 40,
            'mood' => 'motivated',
            'note' => 'Halfway-ish, feeling good',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=checkin-recorded');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('goal_checkins', [
            'goal_id' => $goalId,
            'user_id' => $owner->id,
            'mood' => 'motivated',
        ]);
        // 40% of a target of 100 → current_value 40.
        $this->assertSame(40.0, (float) DB::table('goals')->where('id', $goalId)->value('current_value'));
    }

    public function test_goals_store_checkin_forbidden_for_non_owner(): void
    {
        $owner = $this->makeUser(['name' => 'Checkin POST Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        $this->authenticatedUser(['name' => 'Checkin POST Stranger']);
        $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/checkin", [
            'progress_percent' => 50,
        ])->assertStatus(403);
    }

    // ==================================================================
    //  REMINDERS
    // ==================================================================

    public function test_goals_reminder_form_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Reminder Owner']);
        $goalId = $this->seedGoal($owner->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/reminder");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.reminder.title'));
        $res->assertSee(__('govuk_alpha_goals.reminder.frequency_legend'));
    }

    public function test_goals_save_reminder_persists(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Reminder Saver']);
        $goalId = $this->seedGoal($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/reminder", [
            'frequency' => 'daily',
            'enabled' => '1',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=reminder-saved');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('goal_reminders', [
            'goal_id' => $goalId,
            'user_id' => $owner->id,
            'frequency' => 'daily',
        ]);
    }

    public function test_goals_delete_reminder_removes_row(): void
    {
        $owner = $this->authenticatedUser(['name' => 'Reminder Deleter']);
        $goalId = $this->seedGoal($owner->id);

        // Seed an existing reminder via the service.
        app(\App\Services\GoalReminderService::class)->setReminder($goalId, $owner->id, [
            'frequency' => 'weekly',
            'enabled' => true,
        ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('goal_reminders', ['goal_id' => $goalId, 'user_id' => $owner->id]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/reminder/delete");
        $res->assertRedirect();
        $res->assertRedirectContains('status=reminder-removed');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseMissing('goal_reminders', ['goal_id' => $goalId, 'user_id' => $owner->id]);
    }

    // ==================================================================
    //  BUDDY ACTIONS
    // ==================================================================

    public function test_goals_buddy_actions_renders_for_buddy(): void
    {
        $owner = $this->makeUser(['name' => 'Buddy Goal Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        $buddy = $this->authenticatedUser(['name' => 'The Buddy']);
        $this->assignBuddy($goalId, $buddy->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/buddy-actions");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_goals.buddy.title'));
        $res->assertSee(__('govuk_alpha_goals.buddy_type.offer_help'));
        $res->assertSee('value="encouragement"', false);
    }

    public function test_goals_buddy_actions_forbidden_for_non_buddy(): void
    {
        $owner = $this->makeUser(['name' => 'NB Goal Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        // A signed-in member who is not the buddy is forbidden.
        $this->authenticatedUser(['name' => 'Not The Buddy']);
        $this->get("/{$this->testTenantSlug}/alpha/goals/{$goalId}/buddy-actions")
            ->assertStatus(403);
    }

    public function test_goals_store_buddy_action_persists_note(): void
    {
        $owner = $this->makeUser(['name' => 'Note Goal Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        $buddy = $this->authenticatedUser(['name' => 'Note Buddy']);
        $this->assignBuddy($goalId, $buddy->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/buddy-actions", [
            'type' => 'offer_help',
            'message' => 'Happy to walk with you on Saturday',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=buddy-action-sent');

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertDatabaseHas('goal_buddy_notes', [
            'goal_id' => $goalId,
            'buddy_id' => $buddy->id,
            'owner_id' => $owner->id,
            'type' => 'offer_help',
        ]);
    }

    public function test_goals_store_buddy_action_fails_for_non_buddy(): void
    {
        $owner = $this->makeUser(['name' => 'Reject Goal Owner']);
        $goalId = $this->seedGoal($owner->id, ['is_public' => true]);

        // No buddy assigned → createBuddyNote returns null → failure status.
        $this->authenticatedUser(['name' => 'Reject Buddy']);
        $res = $this->post("/{$this->testTenantSlug}/alpha/goals/{$goalId}/buddy-actions", [
            'type' => 'nudge',
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=buddy-action-failed');
    }
}
