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
 * Feature tests for the accessible (GOV.UK) weekly availability grid.
 * day_of_week is backend-indexed 0=Sunday..6=Saturday; the form posts the
 * correct backend index per slot (Monday => 1, not 0).
 */
class SettingsAvailabilityParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    public function test_availability_page_renders(): void
    {
        $this->authenticatedUser();
        $res = $this->get("/{$this->testTenantSlug}/accessible/settings/availability");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_settings.availability.title'));
        $res->assertSee('Monday');
        $res->assertSee('name="slots[1][0][start]"', false);
    }

    public function test_requires_auth(): void
    {
        $this->get("/{$this->testTenantSlug}/accessible/settings/availability")->assertRedirectContains('/accessible/login');
    }

    public function test_post_saves_recurring_slots_with_correct_day_index(): void
    {
        $user = $this->authenticatedUser();

        $res = $this->post("/{$this->testTenantSlug}/accessible/settings/availability", [
            'slots' => [
                1 => [['start' => '09:00', 'end' => '17:00']], // Monday
                5 => [['start' => '14:00', 'end' => '18:00']], // Friday
                0 => [['start' => '', 'end' => '']],            // Sunday blank
            ],
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=availability-saved');

        $this->assertDatabaseHas('member_availability', [
            'user_id' => $user->id, 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_recurring' => 1,
        ]);
        $this->assertDatabaseHas('member_availability', [
            'user_id' => $user->id, 'day_of_week' => 5, 'start_time' => '14:00:00', 'is_recurring' => 1,
        ]);
        // No Sunday row was created from the blank slot.
        $this->assertSame(0, DB::table('member_availability')->where('user_id', $user->id)->where('day_of_week', 0)->count());
    }

    public function test_invalid_time_is_rejected(): void
    {
        $user = $this->authenticatedUser();

        $res = $this->post("/{$this->testTenantSlug}/accessible/settings/availability", [
            'slots' => [
                1 => [['start' => '17:00', 'end' => '09:00']], // end before start
            ],
        ]);
        $res->assertRedirect();
        $res->assertRedirectContains('status=availability-invalid');
        $this->assertSame(0, DB::table('member_availability')->where('user_id', $user->id)->count());
    }

    public function test_update_replaces_prior_slots(): void
    {
        $user = $this->authenticatedUser();

        // First save.
        $this->post("/{$this->testTenantSlug}/accessible/settings/availability", [
            'slots' => [1 => [['start' => '09:00', 'end' => '17:00']]],
        ])->assertRedirect();

        // Second save: Monday changes, no other days.
        $this->post("/{$this->testTenantSlug}/accessible/settings/availability", [
            'slots' => [1 => [['start' => '08:00', 'end' => '12:00']]],
        ])->assertRedirect();

        $this->assertDatabaseHas('member_availability', [
            'user_id' => $user->id, 'day_of_week' => 1, 'start_time' => '08:00:00', 'is_recurring' => 1,
        ]);
        $this->assertDatabaseMissing('member_availability', [
            'user_id' => $user->id, 'day_of_week' => 1, 'start_time' => '09:00:00',
        ]);
    }
}
