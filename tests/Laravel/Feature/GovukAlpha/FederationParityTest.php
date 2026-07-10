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
 * Federation module — accessible (GOV.UK) frontend parity tests.
 *
 * Covers the React-parity guided "Federation Onboarding" 4-step wizard
 * (govuk-alpha.federation.onboarding) — Welcome -> Privacy -> Communication ->
 * Confirm — that the accessible frontend was missing (it previously had only
 * the single flat /federation/opt-in form). The wizard persists the SAME
 * settings via the SAME service the React FederationOnboardingPage uses, so
 * these tests assert both the per-step UX and the final data outcome.
 *
 * Auth/tenant scrubbing mirrors GovukAlphaFrontendTest::setUp().
 */
class FederationParityTest extends TestCase
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
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Make TenantContext::hasFeature('federation') and the tenant-level
     * federation gate resolve truthy, and whitelist the tenant. Self-contained
     * (does not depend on the federation integration harness).
     */
    private function enableFederationForTenant(?int $tenantId = null): void
    {
        $tenantId ??= $this->testTenantId;

        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                => 1,
                'whitelist_mode_enabled'            => 1,
                'cross_tenant_profiles_enabled'     => 1,
                'cross_tenant_messaging_enabled'    => 1,
                'cross_tenant_transactions_enabled' => 1,
                'cross_tenant_listings_enabled'     => 1,
                'cross_tenant_events_enabled'       => 1,
                'cross_tenant_groups_enabled'       => 1,
                'emergency_lockdown_active'         => 0,
                'updated_at'                        => now(),
            ]
        );

        DB::table('federation_tenant_whitelist')->updateOrInsert(
            ['tenant_id' => $tenantId],
            ['approved_at' => now(), 'approved_by' => 1]
        );

        foreach (['tenant_federation_enabled', 'federation'] as $feature) {
            DB::table('federation_tenant_features')->updateOrInsert(
                ['tenant_id' => $tenantId, 'feature_key' => $feature],
                ['is_enabled' => 1, 'updated_at' => now()]
            );
        }

        try {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if ($tenant) {
                $features = json_decode($tenant->features ?? '{}', true) ?: [];
                $features['federation'] = true;
                DB::table('tenants')->where('id', $tenantId)->update([
                    'features'   => json_encode($features),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal in schemas without the column.
        }

        try {
            app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($tenantId);
        } catch (\Throwable $e) {
            // Optional cache clear.
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function seedPartnerTenant(string $name = 'Accessible Partner'): int
    {
        return (int) DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . substr(uniqid(), -6),
            'is_active' => 1,
            'features' => json_encode(['federation' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPartnership(int $partnerTenantId, array $overrides = []): int
    {
        $data = array_merge([
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $partnerTenantId,
            'status' => 'active',
            'federation_level' => 4,
            'profiles_enabled' => 1,
            'messaging_enabled' => 1,
            'transactions_enabled' => 1,
            'listings_enabled' => 1,
            'events_enabled' => 1,
            'groups_enabled' => 1,
            'requested_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if (DB::getSchemaBuilder()->hasColumn('federation_partnerships', 'canonical_pair')) {
            $data['canonical_pair'] = min($this->testTenantId, $partnerTenantId) . '-' . max($this->testTenantId, $partnerTenantId);
        }

        return (int) DB::table('federation_partnerships')->insertGetId($data);
    }

    private function setFederationSettings(int $userId, array $overrides = []): void
    {
        DB::table('federation_user_settings')->updateOrInsert(
            ['user_id' => $userId],
            array_merge([
                'federation_optin' => 1,
                'profile_visible_federated' => 1,
                'appear_in_federated_search' => 1,
                'show_skills_federated' => 1,
                'show_location_federated' => 0,
                'show_reviews_federated' => 1,
                'messaging_enabled_federated' => 1,
                'transactions_enabled_federated' => 1,
                'email_notifications' => 1,
                'service_reach' => 'remote_ok',
                'travel_radius_km' => 25,
                'updated_at' => now(),
            ], $overrides)
        );
    }

    public function test_federation_onboarding_redirects_anonymous_to_login(): void
    {
        $this->enableFederationForTenant();

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding");

        $response->assertRedirect();
        $response->assertRedirectContains('/accessible/login');
    }

    public function test_federation_onboarding_renders_welcome_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Welcome Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.welcome_title'));
        $response->assertSee(__('govuk_alpha_federation.onboarding.get_started'));
        // Native <progress> drives the step indicator (never colour-only).
        $response->assertSee('<progress', false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_federation_onboarding_renders_privacy_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Privacy Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding?step=privacy");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.privacy_heading'));
        $response->assertSee('name="profile_visible_federated"', false);
        $response->assertSee('name="show_location_federated"', false);
    }

    public function test_federation_onboarding_renders_communication_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Comms Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding?step=communication");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.communication_heading'));
        $response->assertSee('name="service_reach"', false);
        $response->assertSee('name="travel_radius_km"', false);
    }

    public function test_federation_onboarding_renders_confirm_step_with_warning(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Confirm Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding?step=confirm");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.confirm_heading'));
        $response->assertSee(__('govuk_alpha_federation.onboarding.enable_federation'));
        // Data-sharing confirm warning is present before the enable action.
        $response->assertSee('govuk-warning-text', false);
    }

    public function test_federation_onboarding_unknown_step_falls_back_to_welcome(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Fallback Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding?step=not-a-real-step");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.welcome_title'));
    }

    public function test_federation_onboarding_store_advances_step_without_opting_in(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Advance Member']);
        $csrfToken = 'test-csrf-token';

        $response = $this->withSession(['_token' => $csrfToken])
            ->post("/{$this->testTenantSlug}/accessible/federation/onboarding", [
                'step' => 'welcome',
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect(
            route('govuk-alpha.federation.onboarding', ['tenantSlug' => $this->testTenantSlug, 'step' => 'privacy'])
        );

        // Advancing a step must NOT opt the member in yet.
        $this->assertFalse(\App\Services\FederationUserService::hasOptedIn($user->id));
    }

    public function test_federation_onboarding_confirm_opts_member_in_and_persists_settings(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Finish Member']);
        $csrfToken = 'test-csrf-token';

        // Walk the privacy + communication steps so the session bag carries the
        // member's choices into the final confirm submit.
        $this->withSession(['_token' => $csrfToken])
            ->post("/{$this->testTenantSlug}/accessible/federation/onboarding", [
                'step' => 'privacy',
                '_token' => $csrfToken,
                'profile_visible_federated' => '1',
                'appear_in_federated_search' => '1',
                'show_skills_federated' => '1',
                // location deliberately left off
                'show_reviews_federated' => '1',
            ])->assertRedirect();

        $this->post("/{$this->testTenantSlug}/accessible/federation/onboarding", [
            'step' => 'communication',
            '_token' => $csrfToken,
            'messaging_enabled_federated' => '1',
            'transactions_enabled_federated' => '1',
            'email_notifications' => '1',
            'service_reach' => 'travel_ok',
            'travel_radius_km' => '40',
        ])->assertRedirect();

        $finish = $this->post("/{$this->testTenantSlug}/accessible/federation/onboarding", [
            'step' => 'confirm',
            '_token' => $csrfToken,
        ]);

        $finish->assertRedirect(
            route('govuk-alpha.federation.index', ['tenantSlug' => $this->testTenantSlug, 'status' => 'opted-in'])
        );

        // The member is now opted in with the chosen preferences persisted.
        $this->assertTrue(\App\Services\FederationUserService::hasOptedIn($user->id));

        $settings = \App\Services\FederationUserService::getUserSettings($user->id);
        $this->assertTrue((bool) $settings['federation_optin']);
        $this->assertTrue((bool) $settings['profile_visible_federated']);
        $this->assertFalse((bool) $settings['show_location_federated']);
        $this->assertSame('travel_ok', $settings['service_reach']);
        $this->assertSame(40, (int) $settings['travel_radius_km']);
    }

    public function test_federation_onboarding_redirects_opted_in_member_to_hub(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Already In Member']);

        \App\Services\FederationUserService::updateSettings($user->id, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding");

        $response->assertRedirect(
            route('govuk-alpha.federation.index', ['tenantSlug' => $this->testTenantSlug])
        );
    }

    public function test_federation_onboarding_returns_403_when_feature_disabled(): void
    {
        // Federation NOT enabled for the tenant — strip the feature flag.
        try {
            $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
            if ($tenant) {
                $features = json_decode($tenant->features ?? '{}', true) ?: [];
                $features['federation'] = false;
                DB::table('tenants')->where('id', $this->testTenantId)->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
            }
            DB::table('federation_tenant_features')
                ->where('tenant_id', $this->testTenantId)
                ->update(['is_enabled' => 0, 'updated_at' => now()]);
            app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);
        } catch (\Throwable $e) {
            // Best-effort teardown of the flag.
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->authenticatedUser(['name' => 'No Feature Member']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation/onboarding");

        $response->assertForbidden();
    }

    public function test_accessible_hub_links_opted_out_member_to_onboarding_wizard(): void
    {
        // Regression (audit M6): the opted-out hub CTA must point at the guided
        // onboarding wizard (parity with the React opt-in UX), not only the flat
        // opt-in form. Previously the wizard was reachable by URL alone.
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Opted Out Member']);
        // No federation_user_settings row → the member is opted out.

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation");

        $response->assertOk();
        $onboardingUrl = route('govuk-alpha.federation.onboarding', ['tenantSlug' => $this->testTenantSlug]);
        $response->assertSee($onboardingUrl, false);
    }

    public function test_accessible_hub_counts_received_federated_transactions(): void
    {
        $this->enableFederationForTenant();
        $partnerTenantId = $this->seedPartnerTenant('Accessible Transaction Partner');
        $this->enableFederationForTenant($partnerTenantId);
        $this->seedPartnership($partnerTenantId);

        $viewer = $this->authenticatedUser(['name' => 'Receiving Member']);
        $partner = User::factory()->forTenant($partnerTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->setFederationSettings($viewer->id);
        $this->setFederationSettings($partner->id);

        DB::table('transactions')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'sender_id' => $viewer->id,
                'receiver_id' => $partner->id,
                'amount' => 1,
                'description' => 'Sent transfer',
                'status' => 'completed',
                'is_federated' => 1,
                'sender_tenant_id' => $this->testTenantId,
                'receiver_tenant_id' => $partnerTenantId,
                'created_at' => now(),
            ],
            [
                'tenant_id' => $partnerTenantId,
                'sender_id' => $partner->id,
                'receiver_id' => $viewer->id,
                'amount' => 2,
                'description' => 'Received transfer',
                'status' => 'completed',
                'is_federated' => 1,
                'sender_tenant_id' => $partnerTenantId,
                'receiver_tenant_id' => $this->testTenantId,
                'created_at' => now(),
            ],
        ]);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation");

        $response->assertOk();
        $response->assertSeeInOrder([
            __('govuk_alpha.federation.hub.stat_transactions'),
            '2',
        ]);
    }

    public function test_accessible_hub_message_stat_does_not_double_count_dual_insert_copies(): void
    {
        // Regression (audit B8): the send path dual-inserts an outbound (sender
        // copy) and an inbound (receiver copy) row with IDENTICAL sender/receiver
        // columns. The hub stat previously counted both copies (no direction
        // filter), showing ~2x the React figure. A member with 5 sent + 3
        // received must see 8, not 16.
        $this->enableFederationForTenant();
        $partnerTenantId = $this->seedPartnerTenant('Accessible Message Partner');
        $this->enableFederationForTenant($partnerTenantId);
        $this->seedPartnership($partnerTenantId);

        $viewer = $this->authenticatedUser(['name' => 'Messaging Member']);
        $partner = User::factory()->forTenant($partnerTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->setFederationSettings($viewer->id);
        $this->setFederationSettings($partner->id);

        $rows = [];
        // 5 sent by the viewer — each send writes an outbound AND an inbound copy.
        for ($i = 0; $i < 5; $i++) {
            foreach (['outbound', 'inbound'] as $direction) {
                $rows[] = [
                    'sender_tenant_id'   => $this->testTenantId,
                    'sender_user_id'     => $viewer->id,
                    'receiver_tenant_id' => $partnerTenantId,
                    'receiver_user_id'   => $partner->id,
                    'subject'            => 'Sent ' . $i,
                    'body'               => 'Body',
                    'direction'          => $direction,
                    'status'             => $direction === 'inbound' ? 'unread' : 'delivered',
                    'created_at'         => now(),
                ];
            }
        }
        // 3 received by the viewer — dual copies again, viewer on the receiver side.
        for ($i = 0; $i < 3; $i++) {
            foreach (['outbound', 'inbound'] as $direction) {
                $rows[] = [
                    'sender_tenant_id'   => $partnerTenantId,
                    'sender_user_id'     => $partner->id,
                    'receiver_tenant_id' => $this->testTenantId,
                    'receiver_user_id'   => $viewer->id,
                    'subject'            => 'Received ' . $i,
                    'body'               => 'Body',
                    'direction'          => $direction,
                    'status'             => $direction === 'inbound' ? 'unread' : 'delivered',
                    'created_at'         => now(),
                ];
            }
        }
        DB::table('federation_messages')->insert($rows);

        $response = $this->get("/{$this->testTenantSlug}/accessible/federation");

        $response->assertOk();
        $response->assertSeeInOrder([
            __('govuk_alpha.federation.hub.stat_messages'),
            '8',
        ]);
        $response->assertDontSee('16');
    }

    public function test_accessible_federation_read_screens_require_member_opt_in(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Not Opted In Member']);

        $expectedRedirect = route('govuk-alpha.federation.opt-in', ['tenantSlug' => $this->testTenantSlug]);

        foreach ([
            "/{$this->testTenantSlug}/accessible/federation/members",
            "/{$this->testTenantSlug}/accessible/federation/members/123",
            "/{$this->testTenantSlug}/accessible/federation/members/123/transfer",
            "/{$this->testTenantSlug}/accessible/federation/listings",
            "/{$this->testTenantSlug}/accessible/federation/listings/99/123",
            "/{$this->testTenantSlug}/accessible/federation/events",
            "/{$this->testTenantSlug}/accessible/federation/groups",
            "/{$this->testTenantSlug}/accessible/federation/connections",
            "/{$this->testTenantSlug}/accessible/federation/messages",
            "/{$this->testTenantSlug}/accessible/federation/messages/conversation/123",
        ] as $path) {
            $this->get($path)->assertRedirect($expectedRedirect);
        }

        $this->get("/{$this->testTenantSlug}/accessible/federation")->assertOk();
        $this->get("/{$this->testTenantSlug}/accessible/federation/partners")->assertOk();
    }

    public function test_accessible_federation_transfer_is_idempotent_and_stores_raw_description(): void
    {
        $this->enableFederationForTenant();
        $partnerTenantId = $this->seedPartnerTenant('Accessible Transfer Partner');
        $this->enableFederationForTenant($partnerTenantId);
        $this->seedPartnership($partnerTenantId);

        $sender = $this->authenticatedUser(['name' => 'Transfer Sender']);
        $receiver = User::factory()->forTenant($partnerTenantId)->create(['status' => 'active', 'is_approved' => true]);
        DB::table('users')->where('id', $sender->id)->update(['balance' => 10]);
        DB::table('users')->where('id', $receiver->id)->update(['balance' => 0]);
        $this->setFederationSettings($sender->id);
        $this->setFederationSettings($receiver->id);

        $csrfToken = 'transfer-csrf-token';
        $payload = [
            '_token' => $csrfToken,
            'receiver_tenant_id' => $partnerTenantId,
            'amount' => '3',
            'description' => 'Thanks & support <3',
            'idempotency_key' => 'alpha-transfer-dup-1',
        ];

        $this->withSession(['_token' => $csrfToken])
            ->post("/{$this->testTenantSlug}/accessible/federation/members/{$receiver->id}/transfer", $payload)
            ->assertRedirect();

        $this->withSession(['_token' => $csrfToken])
            ->post("/{$this->testTenantSlug}/accessible/federation/members/{$receiver->id}/transfer", $payload)
            ->assertRedirect();

        $rows = DB::table('transactions')
            ->where('sender_id', $sender->id)
            ->where('sender_tenant_id', $this->testTenantId)
            ->where('receiver_id', $receiver->id)
            ->where('receiver_tenant_id', $partnerTenantId)
            ->where('is_federated', 1)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Thanks & support <3', $rows[0]->description);
        $this->assertSame('7.00', (string) DB::table('users')->where('id', $sender->id)->value('balance'));
        $this->assertSame('3.00', (string) DB::table('users')->where('id', $receiver->id)->value('balance'));
    }

    public function test_accessible_federation_message_stores_raw_subject_and_body(): void
    {
        $this->enableFederationForTenant();
        $partnerTenantId = $this->seedPartnerTenant('Accessible Message Partner');
        $this->enableFederationForTenant($partnerTenantId);
        $this->seedPartnership($partnerTenantId);

        $sender = $this->authenticatedUser(['name' => 'Message Sender']);
        $receiver = User::factory()->forTenant($partnerTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $this->setFederationSettings($sender->id);
        $this->setFederationSettings($receiver->id);

        $csrfToken = 'message-csrf-token';
        $this->withSession(['_token' => $csrfToken])
            ->post("/{$this->testTenantSlug}/accessible/federation/messages", [
                '_token' => $csrfToken,
                'receiver_id' => $receiver->id,
                'receiver_tenant_id' => $partnerTenantId,
                'subject' => 'Hello & welcome',
                'body' => 'Use <b>bold</b> & plain text.',
            ])
            ->assertRedirect();

        $message = DB::table('federation_messages')
            ->where('sender_user_id', $sender->id)
            ->where('receiver_user_id', $receiver->id)
            ->where('direction', 'outbound')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame('Hello & welcome', $message->subject);
        $this->assertSame('Use <b>bold</b> & plain text.', $message->body);
    }
}
