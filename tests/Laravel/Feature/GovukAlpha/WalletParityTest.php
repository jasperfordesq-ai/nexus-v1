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
 * Feature coverage for the accessible-frontend wallet parity module — the
 * /wallet/manage hub that surfaces the React-only affordances the core wallet
 * page does not (full pending stat tile, pending badge, ?to= recipient
 * pre-fill, donate recipient-type toggle).
 *
 * Mirrors GovukAlphaFrontendTest's base class, traits and helpers. Every test
 * method is prefixed test_wallet_ and is globally unique. The actual money
 * mutations delegate to the existing wallet.transfer / wallet.donate handlers,
 * which have their own coverage; these tests assert the new GET hub and that
 * its forms point at the canonical handlers and persist when posted.
 */
class WalletParityTest extends TestCase
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
    //  Manage hub — auth + render
    // ==================================================================

    public function test_wallet_manage_requires_auth(): void
    {
        $this->get("/{$this->testTenantSlug}/alpha/wallet/manage")
            ->assertRedirectContains('/alpha/login');
    }

    public function test_wallet_manage_renders_for_member(): void
    {
        $this->authenticatedUser(['balance' => 12]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet/manage");
        $res->assertOk();
        $res->assertHeader('content-type', 'text/html; charset=UTF-8');
        $res->assertSee(__('govuk_alpha_wallet.manage.title'));
        // The full stat grid INCLUDING the pending tile.
        $res->assertSee(__('govuk_alpha_wallet.stats.pending'));
        // The donate recipient-type toggle is rendered as a fieldset of radios.
        $res->assertSee('name="target"', false);
        $res->assertSee(__('govuk_alpha_wallet.donate.target_legend'));
    }

    public function test_wallet_manage_shows_pending_badge(): void
    {
        $user = $this->authenticatedUser(['balance' => 30]);

        // A pending INCOMING transaction should drive the "pending in" badge.
        DB::table('transactions')->insert([
            'tenant_id' => $this->testTenantId,
            'sender_id' => null,
            'receiver_id' => $user->id,
            'amount' => 4,
            'description' => 'Pending grant',
            'status' => 'pending',
            'transaction_type' => 'transfer',
            'created_at' => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet/manage");
        $res->assertOk();
        // The yellow pending tag should appear (count 4) rather than the "no pending" grey tag.
        $res->assertSee('govuk-tag--yellow', false);
        $res->assertDontSee(__('govuk_alpha_wallet.balance.no_pending'));
    }

    public function test_wallet_manage_shows_no_pending_tag_when_empty(): void
    {
        $this->authenticatedUser(['balance' => 5]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet/manage");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_wallet.balance.no_pending'));
    }

    // ==================================================================
    //  Recipient pre-fill (?to=) and search
    // ==================================================================

    public function test_wallet_manage_prefills_recipient_from_to_param(): void
    {
        $this->authenticatedUser(['balance' => 20]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Prefill',
            'last_name' => 'Target',
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet/manage?to={$recipient->id}");
        $res->assertOk();
        // The pre-selected recipient surfaces in the transfer section with a notice.
        $res->assertSee('Prefill Target');
        $res->assertSee(__('govuk_alpha_wallet.transfer.prefill_notice'));
        // And a transfer form pointing at the canonical handler is present.
        $res->assertSee(route('govuk-alpha.wallet.transfer', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_wallet_manage_to_param_ignores_other_tenant_user(): void
    {
        $this->authenticatedUser(['balance' => 20]);
        // A user in a different tenant must never resolve as a recipient. Seed a
        // REAL second tenant first (nexus_test only ships tenants 1 and 2), then
        // place the foreign user in it so the users->tenants FK is satisfied and
        // the cross-tenant rejection is genuinely exercised by the handler's
        // tenant_id scope rather than by an FK error.
        $foreignTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Neighbour Timebank',
            'slug' => 'wallet-foreign-' . strtolower(\Illuminate\Support\Str::random(8)),
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'tagline' => 'A neighbouring community.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreign = User::factory()->forTenant($foreignTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Foreign',
            'last_name' => 'User',
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/wallet/manage?to={$foreign->id}");
        $res->assertOk();
        $res->assertDontSee('Foreign User');
        // With no recipient resolved, the member donate radio is disabled.
        $res->assertSee('id="donate-target-member"', false);
    }

    // ==================================================================
    //  Donate recipient-type toggle → canonical handler
    // ==================================================================

    public function test_wallet_manage_donate_to_fund_posts_to_canonical_handler(): void
    {
        $user = $this->authenticatedUser(['balance' => 25]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/wallet/donate", [
            'target' => 'community_fund',
            'amount' => 3,
            'message' => 'For the pool',
        ]);
        // The canonical donate handler redirects back to the core wallet index.
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        // The donor balance was debited by the canonical CreditDonationService.
        $this->assertSame(22, (int) DB::table('users')->where('id', $user->id)->value('balance'));
    }

    public function test_wallet_manage_donate_to_member_persists(): void
    {
        $donor = $this->authenticatedUser(['balance' => 40]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'balance' => 0,
        ]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/wallet/donate", [
            'target' => 'user',
            'recipient_id' => $recipient->id,
            'amount' => 6,
            'message' => 'Thanks',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertSame(34, (int) DB::table('users')->where('id', $donor->id)->value('balance'));
        $this->assertSame(6, (int) DB::table('users')->where('id', $recipient->id)->value('balance'));
    }

    // ==================================================================
    //  Transfer form → canonical handler (whole-hour amounts on int test DB)
    // ==================================================================

    public function test_wallet_manage_transfer_form_persists_via_canonical_handler(): void
    {
        $sender = $this->authenticatedUser(['balance' => 15]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'balance' => 0,
        ]);

        $res = $this->post("/{$this->testTenantSlug}/alpha/wallet/transfer", [
            'recipient_id' => $recipient->id,
            'amount' => 5,
            'note' => 'Manage-hub transfer',
        ]);
        $res->assertRedirect();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->assertSame(10, (int) DB::table('users')->where('id', $sender->id)->value('balance'));
        $this->assertSame(5, (int) DB::table('users')->where('id', $recipient->id)->value('balance'));
        $this->assertDatabaseHas('transactions', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'status' => 'completed',
        ]);
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
}
