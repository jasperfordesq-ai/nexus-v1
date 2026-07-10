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
 * Parity coverage for the accessible (GOV.UK) saved-collections grid + detail +
 * CRUD, public collections view, and the appreciation wall (view / send /
 * react). Mirrors the auth-gating, tenant-pinning and helper conventions of
 * tests/Laravel/Feature/GovukAlphaFrontendTest.php (which keeps these helpers
 * private), reproduced here so this file stands alone.
 */
class SavedCollectionsParityTest extends TestCase
{
    use DatabaseTransactions;

    protected int $testTenantId = 2;
    protected string $testTenantSlug = 'hour-timebank';

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

    // =====================================================================
    //  My collections — grid + create
    // =====================================================================

    public function test_saved_my_collections_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_saved_my_collections_renders_owned_collections(): void
    {
        $me = $this->authenticatedUser(['name' => 'Collector Me']);

        $colId = $this->seedCollection($me->id, ['name' => 'Skills to learn', 'description' => 'A reading list', 'is_public' => true, 'items_count' => 3]);
        $this->seedCollection($me->id, ['name' => 'Private stash', 'is_public' => false]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_saved.collections.title'));
        $response->assertSee('Skills to learn');
        $response->assertSee('Private stash');
        $response->assertSee(__('govuk_alpha_saved.collections.public_tag'));
        $response->assertSee(route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $this->testTenantSlug, 'id' => $colId]), false);
    }

    public function test_saved_my_collections_shows_empty_state(): void
    {
        $this->authenticatedUser(['name' => 'Empty Collector']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_saved.collections.empty_title'));
        $response->assertSee(__('govuk_alpha_saved.create.heading'));
    }

    public function test_saved_create_collection_persists_and_redirects(): void
    {
        $me = $this->authenticatedUser(['name' => 'Maker Me']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections", [
            'name' => 'Brand new list',
            'description' => 'Things I want to do',
            'is_public' => '1',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.collections', [
            'tenantSlug' => $this->testTenantSlug,
            'status' => 'collection-created',
        ]));

        $this->assertTrue(
            DB::table('saved_collections')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $me->id)
                ->where('name', 'Brand new list')
                ->where('is_public', 1)
                ->exists(),
            'Expected the new collection to persist for the owner'
        );
    }

    public function test_saved_create_collection_requires_name(): void
    {
        $this->authenticatedUser(['name' => 'No Name Me']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections", [
            'name' => '   ',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.collections', [
            'tenantSlug' => $this->testTenantSlug,
            'status' => 'collection-name-required',
        ]));
    }

    // =====================================================================
    //  Collection detail + items + CRUD
    // =====================================================================

    public function test_saved_collection_detail_renders_items_for_owner(): void
    {
        $me = $this->authenticatedUser(['name' => 'Detail Me']);
        $colId = $this->seedCollection($me->id, ['name' => 'Saved listings', 'items_count' => 1]);
        $this->seedItem($colId, $me->id, ['item_type' => 'listing', 'item_id' => 123, 'note' => 'Looks useful']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections/{$colId}");

        $response->assertOk();
        $response->assertSee('Saved listings');
        $response->assertSee('Looks useful');
        // Owner sees the remove control.
        $response->assertSee(__('govuk_alpha_saved.detail.remove_item'));
        $response->assertSee(__('govuk_alpha_saved.edit.heading'));
    }

    public function test_saved_collection_detail_non_existent_returns_404(): void
    {
        $this->authenticatedUser(['name' => 'Detail 404']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections/99999999");

        $response->assertNotFound();
    }

    public function test_saved_collection_detail_private_non_owner_returns_403(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Private Owner']);
        $colId = $this->seedCollection($owner->id, ['name' => 'Secret list', 'is_public' => false]);

        // A different, authenticated user tries to view the private collection.
        $this->authenticatedUser(['name' => 'Nosy Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections/{$colId}");

        $response->assertForbidden();
    }

    public function test_saved_collection_detail_public_visible_to_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Sharer']);
        $colId = $this->seedCollection($owner->id, ['name' => 'Shared picks', 'is_public' => true]);

        $this->authenticatedUser(['name' => 'Other Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/me/collections/{$colId}");

        $response->assertOk();
        $response->assertSee('Shared picks');
        // A non-owner does not see the owner-only edit/remove controls.
        $response->assertDontSee(__('govuk_alpha_saved.edit.delete_submit'));
    }

    public function test_saved_remove_item_deletes_for_owner(): void
    {
        $me = $this->authenticatedUser(['name' => 'Remover Me']);
        $colId = $this->seedCollection($me->id, ['name' => 'Trim me', 'items_count' => 1]);
        $itemId = $this->seedItem($colId, $me->id, ['item_type' => 'event', 'item_id' => 55]);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections/{$colId}/items/{$itemId}/remove");

        $response->assertRedirect(route('govuk-alpha.saved.collection-detail', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $colId,
            'status' => 'item-removed',
        ]));

        $this->assertDatabaseMissing('saved_items', ['id' => $itemId]);
    }

    public function test_saved_update_collection_persists_changes(): void
    {
        $me = $this->authenticatedUser(['name' => 'Editor Me']);
        $colId = $this->seedCollection($me->id, ['name' => 'Old name', 'is_public' => false]);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections/{$colId}/update", [
            'name' => 'New name',
            'description' => 'Updated description',
            'is_public' => '1',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.collection-detail', [
            'tenantSlug' => $this->testTenantSlug,
            'id' => $colId,
            'status' => 'collection-updated',
        ]));

        $this->assertTrue(
            DB::table('saved_collections')
                ->where('id', $colId)
                ->where('user_id', $me->id)
                ->where('name', 'New name')
                ->where('is_public', 1)
                ->exists()
        );
    }

    public function test_saved_update_collection_non_owner_returns_404(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $colId = $this->seedCollection($owner->id, ['name' => 'Not yours']);

        $this->authenticatedUser(['name' => 'Hijacker']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections/{$colId}/update", [
            'name' => 'Hijacked',
        ]);

        $response->assertNotFound();
    }

    public function test_saved_delete_collection_removes_it(): void
    {
        $me = $this->authenticatedUser(['name' => 'Deleter Me']);
        $colId = $this->seedCollection($me->id, ['name' => 'Bin me']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/me/collections/{$colId}/delete");

        $response->assertRedirect(route('govuk-alpha.saved.collections', [
            'tenantSlug' => $this->testTenantSlug,
            'status' => 'collection-deleted',
        ]));

        $this->assertDatabaseMissing('saved_collections', ['id' => $colId]);
    }

    // =====================================================================
    //  Public collections of another member
    // =====================================================================

    public function test_saved_public_collections_requires_authentication(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/{$owner->id}/collections");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_saved_public_collections_shows_only_public(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Owner Pub']);
        $this->seedCollection($owner->id, ['name' => 'Visible to all', 'is_public' => true]);
        $this->seedCollection($owner->id, ['name' => 'Hidden away', 'is_public' => false]);

        $this->authenticatedUser(['name' => 'Public Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/{$owner->id}/collections");

        $response->assertOk();
        $response->assertSee('Visible to all');
        $response->assertDontSee('Hidden away');
    }

    public function test_saved_public_collections_unknown_member_returns_404(): void
    {
        $this->authenticatedUser(['name' => 'Pub 404']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/99999999/collections");

        $response->assertNotFound();
    }

    // =====================================================================
    //  Appreciation wall — view / send / react
    // =====================================================================

    public function test_saved_appreciation_wall_requires_authentication(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/{$owner->id}/appreciations");

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    public function test_saved_appreciation_wall_renders_public_notes(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Wall Owner']);
        $sender = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Thankful Tara']);
        $this->seedAppreciation($sender->id, $owner->id, ['message' => 'Thanks for the wonderful help', 'is_public' => true]);

        $this->authenticatedUser(['name' => 'Wall Viewer']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/{$owner->id}/appreciations");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_saved.wall.heading', ['name' => 'Wall Owner']));
        $response->assertSee('Thanks for the wonderful help');
        $response->assertSee('Thankful Tara');
        // Reaction buttons render.
        $response->assertSee(__('govuk_alpha_saved.react.heart'));
        // The viewer (not the owner) sees a send form.
        $response->assertSee(__('govuk_alpha_saved.send.submit'));
    }

    public function test_saved_appreciation_wall_hides_send_form_on_own_wall(): void
    {
        $me = $this->authenticatedUser(['name' => 'Self Wall']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/{$me->id}/appreciations");

        $response->assertOk();
        $response->assertDontSee(__('govuk_alpha_saved.send.submit'));
    }

    public function test_saved_appreciation_wall_unknown_member_returns_404(): void
    {
        $this->authenticatedUser(['name' => 'Wall 404']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/users/99999999/appreciations");

        $response->assertNotFound();
    }

    public function test_saved_send_appreciation_persists_and_notifies(): void
    {
        $me = $this->authenticatedUser(['name' => 'Sender Me']);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Recipient']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/users/{$owner->id}/appreciations", [
            'message' => 'You were brilliant, thank you so much',
            'is_public' => '1',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.appreciations', [
            'tenantSlug' => $this->testTenantSlug,
            'userId' => $owner->id,
            'status' => 'appreciation-sent',
        ]));

        $this->assertTrue(
            DB::table('appreciations')
                ->where('tenant_id', $this->testTenantId)
                ->where('sender_id', $me->id)
                ->where('receiver_id', $owner->id)
                ->where('message', 'You were brilliant, thank you so much')
                ->exists(),
            'Expected the appreciation to persist'
        );
    }

    public function test_saved_send_appreciation_requires_message(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->authenticatedUser(['name' => 'Blank Sender']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/users/{$owner->id}/appreciations", [
            'message' => '   ',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.appreciations', [
            'tenantSlug' => $this->testTenantSlug,
            'userId' => $owner->id,
            'status' => 'appreciation-message-required',
        ]));
    }

    public function test_saved_send_appreciation_rejects_self(): void
    {
        $me = $this->authenticatedUser(['name' => 'Self Sender']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/users/{$me->id}/appreciations", [
            'message' => 'Thanking myself',
        ]);

        $response->assertRedirect(route('govuk-alpha.saved.appreciations', [
            'tenantSlug' => $this->testTenantSlug,
            'userId' => $me->id,
            'status' => 'appreciation-self',
        ]));

        $this->assertFalse(
            DB::table('appreciations')
                ->where('sender_id', $me->id)
                ->where('receiver_id', $me->id)
                ->exists()
        );
    }

    public function test_saved_react_appreciation_creates_reaction(): void
    {
        $me = $this->authenticatedUser(['name' => 'Reactor Me']);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'React Owner']);
        $sender = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $apprId = $this->seedAppreciation($sender->id, $owner->id, ['message' => 'Cheers', 'is_public' => true]);

        $response = $this->post("/{$this->testTenantSlug}/accessible/appreciations/{$apprId}/react", [
            'reaction_type' => 'heart',
            'owner_id' => $owner->id,
        ]);

        $response->assertRedirect(
            route('govuk-alpha.saved.appreciations', [
                'tenantSlug' => $this->testTenantSlug,
                'userId' => $owner->id,
                'status' => 'reaction-updated',
            ]) . '#appreciation-' . $apprId
        );

        $this->assertTrue(
            DB::table('appreciation_reactions')
                ->where('appreciation_id', $apprId)
                ->where('user_id', $me->id)
                ->where('reaction_type', 'heart')
                ->exists()
        );
    }

    public function test_saved_react_appreciation_non_existent_returns_404(): void
    {
        $this->authenticatedUser(['name' => 'React 404']);

        $response = $this->post("/{$this->testTenantSlug}/accessible/appreciations/99999999/react", [
            'reaction_type' => 'heart',
        ]);

        $response->assertNotFound();
    }

    public function test_saved_react_appreciation_requires_authentication(): void
    {
        $response = $this->post("/{$this->testTenantSlug}/accessible/appreciations/1/react", [
            'reaction_type' => 'heart',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }

    // =====================================================================
    //  Helpers
    // =====================================================================

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function seedCollection(int $userId, array $overrides = []): int
    {
        return DB::table('saved_collections')->insertGetId(array_merge([
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'name' => 'Test collection',
            'description' => null,
            'is_public' => false,
            'color' => '#6366f1',
            'icon' => 'bookmark',
            'items_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function seedItem(int $collectionId, int $userId, array $overrides = []): int
    {
        return DB::table('saved_items')->insertGetId(array_merge([
            'collection_id' => $collectionId,
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'item_type' => 'listing',
            'item_id' => 1,
            'note' => null,
            'saved_at' => now(),
        ], $overrides));
    }

    private function seedAppreciation(int $senderId, int $receiverId, array $overrides = []): int
    {
        return DB::table('appreciations')->insertGetId(array_merge([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'tenant_id' => $this->testTenantId,
            'message' => 'Thank you',
            'context_type' => null,
            'context_id' => null,
            'is_public' => true,
            'reactions_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
