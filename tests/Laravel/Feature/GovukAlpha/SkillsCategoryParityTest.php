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
 * Feature tests for the accessible (GOV.UK) Skills directory parity work:
 * category drill-in with member/offering/requesting counts, and proficiency /
 * offers / wants tags on the members-with-skill panel.
 *
 * Method names are prefixed test_skills_*.
 */
class SkillsCategoryParityTest extends TestCase
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
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function seedCategory(string $name): int
    {
        return (int) DB::table('skill_categories')->insertGetId([
            'tenant_id'     => $this->testTenantId,
            'name'          => $name,
            'slug'          => 'parity-' . uniqid(),
            'parent_id'     => null,
            'description'   => 'Parity test category',
            'display_order' => 0,
            'is_active'     => 1,
        ]);
    }

    private function seedSkill(int $userId, int $categoryId, string $skill, array $overrides = []): void
    {
        DB::table('user_skills')->insert(array_merge([
            'tenant_id'     => $this->testTenantId,
            'user_id'       => $userId,
            'category_id'   => $categoryId,
            'skill_name'    => $skill,
            'proficiency'   => 'advanced',
            'is_offering'   => 1,
            'is_requesting' => 0,
        ], $overrides));
    }

    public function test_skills_index_lists_categories_as_drill_in_links(): void
    {
        $this->authenticatedUser();
        $catId = $this->seedCategory('Gardening Parity');

        $response = $this->get("/{$this->testTenantSlug}/accessible/skills");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.skills.browse_by_category'));
        $response->assertSee('Gardening Parity');
        $response->assertSee("category={$catId}", false);
    }

    public function test_skills_category_drill_in_shows_counts(): void
    {
        $owner = $this->authenticatedUser();
        $catId = $this->seedCategory('Repairs Parity');

        $offerer = $this->authenticatedUser();
        $requester = $this->authenticatedUser();
        // Re-authenticate as the original viewer (any active user can browse).
        Sanctum::actingAs($owner, ['*']);

        $this->seedSkill($offerer->id, $catId, 'Plumbing Parity', ['is_offering' => 1, 'is_requesting' => 0]);
        $this->seedSkill($requester->id, $catId, 'Plumbing Parity', ['is_offering' => 0, 'is_requesting' => 1]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/skills?category={$catId}");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.skills.skills_in', ['category' => 'Repairs Parity']));
        $response->assertSee(__('govuk_alpha.skills.col_offering'));
        $response->assertSee('Plumbing Parity');
        $response->assertSee(__('govuk_alpha.skills.back_to_categories'));
    }

    public function test_skills_member_panel_shows_proficiency_and_tags(): void
    {
        $viewer = $this->authenticatedUser();
        $catId = $this->seedCategory('Tutoring Parity');

        $member = $this->authenticatedUser(['first_name' => 'Skillful', 'last_name' => 'Member']);
        Sanctum::actingAs($viewer, ['*']);

        $this->seedSkill($member->id, $catId, 'Algebra Parity', [
            'proficiency'   => 'expert',
            'is_offering'   => 1,
            'is_requesting' => 1,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/skills?skill=" . urlencode('Algebra Parity'));

        $response->assertOk();
        $response->assertSee('Skillful Member');
        $response->assertSee(__('govuk_alpha.skills.proficiency.expert'));
        $response->assertSee(__('govuk_alpha.skills.offers'));
        $response->assertSee(__('govuk_alpha.skills.wants'));
    }
}
