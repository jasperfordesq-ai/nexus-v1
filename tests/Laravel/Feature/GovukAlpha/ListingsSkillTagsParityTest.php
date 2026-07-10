<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Events\ListingCreated;
use App\Events\ListingUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for accessible (GOV.UK) listing skill-tags on the create/edit
 * forms — persisted via ListingSkillTagService::setTags, prefilled via getTags.
 */
class ListingsSkillTagsParityTest extends TestCase
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
        $this->enableModule('listings');

        // Stop ListingCreated/Updated listeners (Meilisearch index + broadcast)
        // from making network calls that hang the test env. The listing + its
        // skill tags still persist — those are written directly, not via events.
        Event::fake([ListingCreated::class, ListingUpdated::class]);
    }

    private function enableModule(string $module): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('configuration');
        $config = $row ? (json_decode($row, true) ?: []) : [];
        $config['modules'] = $config['modules'] ?? [];
        $config['modules'][$module] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['configuration' => json_encode($config)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function seedCategory(): int
    {
        return (int) DB::table('categories')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name'      => 'Gardening',
            'slug'      => 'gardening-' . uniqid(),
            'type'      => 'listing',
        ]);
    }

    public function test_create_listing_persists_skill_tags(): void
    {
        $this->authenticatedUser();
        $categoryId = $this->seedCategory();

        $res = $this->post("/{$this->testTenantSlug}/accessible/listings/new", [
            'title'       => 'Help with the garden please',
            'description' => 'I can offer help with general gardening and planting work.',
            'type'        => 'offer',
            'category_id' => $categoryId,
            'skill_tags'  => 'Plumbing, Electrical',
        ]);
        $res->assertRedirect();

        $listingId = (int) DB::table('listings')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', 'Help with the garden please')
            ->value('id');
        $this->assertGreaterThan(0, $listingId);

        // Service normalises to lowercase.
        $this->assertDatabaseHas('listing_skill_tags', ['listing_id' => $listingId, 'tag' => 'plumbing']);
        $this->assertDatabaseHas('listing_skill_tags', ['listing_id' => $listingId, 'tag' => 'electrical']);
    }

    public function test_edit_listing_prefills_and_updates_skill_tags(): void
    {
        $this->authenticatedUser();
        $categoryId = $this->seedCategory();

        // Create through the real flow so the tags persist under the request's
        // tenant binding (matches production), then edit it.
        $this->post("/{$this->testTenantSlug}/accessible/listings/new", [
            'title'       => 'Editable Listing Item',
            'description' => 'An initial description for the editable listing item.',
            'type'        => 'offer',
            'category_id' => $categoryId,
            'skill_tags'  => 'Plumbing, Electrical',
        ])->assertRedirect();

        $listingId = (int) DB::table('listings')
            ->where('tenant_id', $this->testTenantId)
            ->where('title', 'Editable Listing Item')
            ->value('id');
        $this->assertGreaterThan(0, $listingId);

        // Edit form prefills existing tags (getTags returns them sorted).
        $res = $this->get("/{$this->testTenantSlug}/accessible/listings/{$listingId}/edit");
        $res->assertOk();
        $res->assertSee('name="skill_tags"', false);
        $res->assertSee('electrical, plumbing', false);

        // Update replaces them.
        $upd = $this->post("/{$this->testTenantSlug}/accessible/listings/{$listingId}/edit", [
            'title'       => 'Editable Listing Item',
            'description' => 'An updated description for the editable listing item.',
            'type'        => 'offer',
            'category_id' => $categoryId,
            'skill_tags'  => 'hvac',
        ]);
        $upd->assertRedirect();

        $this->assertDatabaseHas('listing_skill_tags', ['listing_id' => $listingId, 'tag' => 'hvac']);
        $this->assertDatabaseMissing('listing_skill_tags', ['listing_id' => $listingId, 'tag' => 'plumbing']);
    }
}
