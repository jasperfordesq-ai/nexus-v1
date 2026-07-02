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
 * Parity coverage for the accessible (GOV.UK) connections "My network" page and
 * the cross-module matches board, plus the reason-aware match dismiss.
 *
 * Mirrors the auth-gating, tenant-pinning and helper conventions of
 * tests/Laravel/Feature/GovukAlphaFrontendTest.php (which keeps these helpers
 * private), reproduced here so this file stands alone.
 */
class ConnectionsMatchesParityTest extends TestCase
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
    //  Connections "My network" page
    // =====================================================================

    public function test_connections_network_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_connections_network_renders_three_sections_with_data(): void
    {
        $me = $this->authenticatedUser(['name' => 'Net Me']);
        $requester = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Nadia Network']);
        $sentTo = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Sven Sentto']);
        $friend = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Fred Friendnet']);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $requester->id, 'receiver_id' => $me->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'requester_id' => $me->id, 'receiver_id' => $sentTo->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now()->subMonths(3), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_connections.network.title'));
        $response->assertSee(__('govuk_alpha_connections.network.tab_accepted'));
        $response->assertSee(__('govuk_alpha_connections.network.tab_pending_received'));
        $response->assertSee(__('govuk_alpha_connections.network.tab_pending_sent'));
        $response->assertSee('Nadia Network');  // pending received
        $response->assertSee('Sven Sentto');     // pending sent
        $response->assertSee('Fred Friendnet');  // accepted
    }

    public function test_connections_network_shows_connected_since_and_message_button(): void
    {
        $me = $this->authenticatedUser(['name' => 'Date Me']);
        $friend = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Greta Greatfriend']);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now()->subMonths(2), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertOk();
        // Connected-since label (the date string is locale-formatted; assert the key prefix).
        $sinceDate = now()->subMonths(2)->translatedFormat('F Y');
        $response->assertSee(__('govuk_alpha_connections.network.connected_since', ['date' => $sinceDate]));
        // Message action links to the conversation composer for that member.
        $response->assertSee(route('govuk-alpha.messages.new', ['tenantSlug' => $this->testTenantSlug, 'userId' => $friend->id]), false);
        $response->assertSee(__('govuk_alpha_connections.network.message'));
    }

    public function test_connections_network_shows_partner_bio_excerpt(): void
    {
        $me = $this->authenticatedUser(['name' => 'Bio Me']);
        $friend = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Bridget Bioholder',
            'bio' => '<p>Keen gardener and woodwork tutor.</p>',
        ]);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertOk();
        // HTML is stripped from the stored bio before rendering (parity with
        // React's stripHtmlToText), so the plain-text excerpt is shown without tags.
        $response->assertSee('Keen gardener and woodwork tutor.');
        $response->assertDontSee('<p>Keen gardener', false);
        $response->assertSee(__('govuk_alpha_connections.network.about', ['name' => 'Bridget Bioholder']));
    }

    public function test_connections_index_shows_partner_bio_excerpt(): void
    {
        $me = $this->authenticatedUser(['name' => 'Index Bio Me']);
        $friend = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'Cormac Cardbio',
            'bio' => 'Cycling buddy and bread baker.',
        ]);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections");

        $response->assertOk();
        $response->assertSee('Cycling buddy and bread baker.');
    }

    public function test_connections_network_search_filters_by_name(): void
    {
        $me = $this->authenticatedUser(['name' => 'Search Me']);
        $keep = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Wendy Wanted']);
        $drop = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Oliver Otherperson']);

        DB::table('connections')->insert([
            ['tenant_id' => $this->testTenantId, 'requester_id' => $keep->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'requester_id' => $drop->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network?q=Wanted");

        $response->assertOk();
        $response->assertSee('Wendy Wanted');
        $response->assertDontSee('Oliver Otherperson');
    }

    public function test_connections_network_paginates_with_load_more(): void
    {
        $me = $this->authenticatedUser(['name' => 'Page Me']);

        // 22 accepted connections — more than the 20 per-page so a load-more link appears.
        $rows = [];
        for ($i = 0; $i < 22; $i++) {
            $friend = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => "Pager {$i}"]);
            $rows[] = ['tenant_id' => $this->testTenantId, 'requester_id' => $friend->id, 'receiver_id' => $me->id, 'status' => 'accepted', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('connections')->insert($rows);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertOk();
        // The accepted section offers a cursor-driven "Show more".
        $response->assertSee(__('govuk_alpha_connections.network.load_more'));
        $response->assertSee('cursor=', false);
    }

    public function test_connections_network_shows_empty_states(): void
    {
        $this->authenticatedUser(['name' => 'Empty Me']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/connections/network");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_connections.network_empty.accepted_title'));
        $response->assertSee(__('govuk_alpha_connections.network_empty.received_title'));
        $response->assertSee(__('govuk_alpha_connections.network_empty.sent_title'));
        // Empty accepted/sent sections surface a "Find members" CTA.
        $response->assertSee(__('govuk_alpha_connections.network_empty.find_members'));
    }

    // =====================================================================
    //  Matches board
    // =====================================================================

    public function test_connections_matches_board_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/matches/board");

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_connections_matches_board_renders_stats_and_source_tabs(): void
    {
        $this->authenticatedUser(['name' => 'Board Viewer']);

        // Renders whether or not the engine returns matches (degrades to empty state).
        $response = $this->get("/{$this->testTenantSlug}/alpha/matches/board");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_connections.matches.title'));
        $response->assertSee(__('govuk_alpha_connections.matches.description'));
    }

    public function test_connections_matches_board_shows_dashboard_when_matches_exist(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Stats Viewer']);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true, 'name' => 'Match Owner']);

        // Seed a listing for the owner and one for the viewer so the engine can
        // find at least one cross-listing match. remote_only is deliberate:
        // neither factory user has coordinates, and the v2 engine's geo hard
        // gate correctly excludes PHYSICAL listings for a no-location searcher
        // (degraded remote-only mode) — a remote pair matches deterministically.
        $this->seedActiveListing($owner->id, ['title' => 'Beginner guitar lessons offer', 'description' => 'I can teach guitar', 'type' => 'offer', 'service_type' => 'remote_only']);
        $this->seedActiveListing($viewer->id, ['title' => 'Want guitar lessons', 'description' => 'Looking for guitar help', 'type' => 'request', 'service_type' => 'remote_only']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/matches/board");

        $response->assertOk();
        // Source filter tabs are always present in the legend even when empty.
        $response->assertSee(__('govuk_alpha_connections.matches.source_legend'));
    }

    public function test_connections_matches_board_empty_state_has_browse_cta(): void
    {
        // The matches engine surfaces TENANT-WIDE recommendations (cold-start
        // listings, every approved volunteering org, future events) regardless of
        // what the viewer owns — so tenant 2 (hour-timebank), which carries the
        // bulk of the seed data, never produces a genuinely empty board. To assert
        // the empty state deterministically we use the clean secondary tenant
        // (id 999 / slug 'test-999') that the base TestCase already seeds with no
        // listings, groups, volunteering orgs or events. This keeps the assertion
        // robust across environments instead of depending on a single match source
        // happening to be empty.
        $emptyTenantId = 999;
        $emptyTenantSlug = 'test-999';

        TenantContext::reset();
        TenantContext::setById($emptyTenantId);

        $user = User::factory()->forTenant($emptyTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'name' => 'No Matches Viewer',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->get("/{$emptyTenantSlug}/alpha/matches/board");

        $response->assertOk();
        // With no matches, the empty state and Browse Listings CTA render.
        $response->assertSee(__('govuk_alpha_connections.matches_empty.browse_listings'));
        $response->assertSee(route('govuk-alpha.listings.index', ['tenantSlug' => $emptyTenantSlug]), false);
    }

    // =====================================================================
    //  Matches board dismiss (reason-aware)
    // =====================================================================

    public function test_connections_dismiss_match_creates_dismissal_with_reason(): void
    {
        $user = $this->authenticatedUser(['name' => 'Board Dismisser']);
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listingId = $this->seedActiveListing($owner->id, ['title' => 'Painting offer']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/matches/board/{$listingId}/dismiss", [
            'reason' => 'too_far',
            'source' => 'listing',
        ]);

        $response->assertRedirect(
            route('govuk-alpha.connections.matches-board', [
                'tenantSlug' => $this->testTenantSlug,
                'source' => 'listing',
                'status' => 'match-dismissed',
            ]) . '#matches-top'
        );

        $this->assertTrue(
            DB::table('match_dismissals')
                ->where('tenant_id', $this->testTenantId)
                ->where('user_id', $user->id)
                ->where('listing_id', $listingId)
                ->where('reason', 'too_far')
                ->exists(),
            'Expected a match_dismissals row with the chosen reason'
        );
    }

    public function test_connections_dismiss_match_non_existent_listing_returns_404(): void
    {
        $this->authenticatedUser(['name' => 'Board Dismiss 404']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/matches/board/999999/dismiss", [
            'reason' => 'not_relevant',
        ]);

        $response->assertNotFound();
    }

    public function test_connections_dismiss_match_requires_authentication(): void
    {
        $response = $this->post("/{$this->testTenantSlug}/alpha/matches/board/1/dismiss", [
            'reason' => 'not_relevant',
        ]);

        $response->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    // =====================================================================
    //  Helpers (mirrored from GovukAlphaFrontendTest, which keeps them private)
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

    private function ensureListingCategory(): void
    {
        DB::table('categories')->insertOrIgnore([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'name' => 'General',
            'slug' => 'general',
            'type' => 'listing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedActiveListing(int $ownerId, array $overrides = []): int
    {
        $this->ensureListingCategory();

        return DB::table('listings')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $ownerId,
            'title'       => 'Test listing',
            'description' => 'A test listing for parity tests.',
            'type'        => 'offer',
            'status'      => 'active',
            'category_id' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
    }
}
