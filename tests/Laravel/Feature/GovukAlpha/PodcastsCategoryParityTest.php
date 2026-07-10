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
 * Feature tests for the accessible (GOV.UK) podcasts category filter.
 */
class PodcastsCategoryParityTest extends TestCase
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

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['podcasts'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedShow(int $ownerId, string $title, ?string $category): int
    {
        return (int) DB::table('podcast_shows')->insertGetId([
            'tenant_id'         => $this->testTenantId,
            'owner_user_id'     => $ownerId,
            'title'             => $title,
            'slug'              => 'show-' . uniqid(),
            'language'          => 'en',
            'category'          => $category,
            'visibility'        => 'public',
            'status'            => 'published',
            'moderation_status' => 'approved',
            'published_at'      => now(),
            'episode_count'     => 0,
            'subscriber_count'  => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function test_category_filter_excludes_other_categories(): void
    {
        $owner = $this->authenticatedUser();
        $this->seedShow($owner->id, 'Tech Talk Weekly', 'Technology');
        $this->seedShow($owner->id, 'Garden Hour', 'Gardening');

        $res = $this->get("/{$this->testTenantSlug}/accessible/podcasts?category=Technology");
        $res->assertOk();
        $res->assertSee('Tech Talk Weekly');
        $res->assertDontSee('Garden Hour');
    }

    public function test_category_dropdown_lists_distinct_categories(): void
    {
        $owner = $this->authenticatedUser();
        $this->seedShow($owner->id, 'Tech Talk', 'Technology');
        $this->seedShow($owner->id, 'Garden Hour', 'Gardening');

        $res = $this->get("/{$this->testTenantSlug}/accessible/podcasts");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha.polish_commerce.podcast_category_label'));
        $res->assertSee('name="category"', false);
        $res->assertSee('Technology');
        $res->assertSee('Gardening');
    }

    public function test_invalid_category_is_ignored(): void
    {
        $owner = $this->authenticatedUser();
        $this->seedShow($owner->id, 'Only Show', 'Technology');

        $res = $this->get("/{$this->testTenantSlug}/accessible/podcasts?category=DoesNotExist");
        $res->assertOk();
        $res->assertSee('Only Show');
    }
}
