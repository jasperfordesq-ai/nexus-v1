<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Safeguarding;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\FederationFeatureService;
use App\Services\SafeguardingJurisdictionService;
use App\Services\SafeguardingTriggerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

class SafeguardingFederationPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    public function test_v2_cross_tenant_message_to_protected_recipient_is_denied_without_writes(): void
    {
        $recipientTenantId = $this->createFederatedTenant('Protected federation tenant');
        $this->createPartnership($recipientTenantId);

        $sender = $this->createFederatedUser($this->testTenantId);
        $recipient = $this->createFederatedUser($recipientTenantId);
        $this->protectRecipient($recipient, $recipientTenantId, configureEnglandWales: true);

        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($sender, ['*']);

        $response = $this->postJson('/api/v2/federation/messages', [
            'receiver_id' => $recipient->id,
            'receiver_tenant_id' => $recipientTenantId,
            'subject' => 'Protected contact',
            'body' => 'This must not be delivered.',
        ], [
            'X-Tenant-ID' => (string) $this->testTenantId,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('federation_messages', [
            'sender_user_id' => $sender->id,
            'receiver_user_id' => $recipient->id,
            'subject' => 'Protected contact',
        ]);
    }

    public function test_v2_protected_recipient_with_unconfigured_jurisdiction_fails_unavailable(): void
    {
        $recipientTenantId = $this->createFederatedTenant('Unconfigured federation tenant');
        $this->createPartnership($recipientTenantId);

        $sender = $this->createFederatedUser($this->testTenantId);
        $recipient = $this->createFederatedUser($recipientTenantId);
        $this->protectRecipient($recipient, $recipientTenantId, configureEnglandWales: false);

        TenantContext::setById($this->testTenantId);
        Sanctum::actingAs($sender, ['*']);

        $response = $this->postJson('/api/v2/federation/messages', [
            'receiver_id' => $recipient->id,
            'receiver_tenant_id' => $recipientTenantId,
            'subject' => 'Unavailable policy',
            'body' => 'This must fail closed.',
        ], [
            'X-Tenant-ID' => (string) $this->testTenantId,
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertDatabaseMissing('federation_messages', [
            'sender_user_id' => $sender->id,
            'receiver_user_id' => $recipient->id,
            'subject' => 'Unavailable policy',
        ]);
    }

    public function test_external_webhook_cannot_deliver_to_protected_recipient(): void
    {
        $this->enableFederationForTenant($this->testTenantId);
        $partner = $this->setupPartner('nexus', $this->testTenantId);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->optInUserToFederation($recipient->id);
        $this->protectRecipient($recipient, $this->testTenantId, configureEnglandWales: true);

        $response = $this->simulateInboundWebhook($partner, 'message.sent', [
            'recipient_id' => $recipient->id,
            'sender_id' => 'external-member-44',
            'sender_name' => 'External member',
            'body' => 'This must not be delivered.',
            'external_message_id' => 'protected-' . uniqid(),
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('federation_messages', [
            'receiver_user_id' => $recipient->id,
            'body' => 'This must not be delivered.',
        ]);
        $this->assertNull(
            DB::table('federation_external_partners')->where('id', $partner->id)->value('last_message_at')
        );
    }

    private function createFederatedTenant(string $name): int
    {
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => 'safeguarding-fed-' . substr(uniqid(), -8),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->enableFederationForTenant($this->testTenantId);
        $this->enableFederationForTenant($tenantId);
        app(FederationFeatureService::class)->clearCache();

        return $tenantId;
    }

    private function createPartnership(int $recipientTenantId): void
    {
        $row = [
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $recipientTenantId,
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
        ];

        if ($this->columnExists('federation_partnerships', 'canonical_pair')) {
            $row['canonical_pair'] = min($this->testTenantId, $recipientTenantId)
                . '-' . max($this->testTenantId, $recipientTenantId);
        }

        DB::table('federation_partnerships')->insert($row);
    }

    private function createFederatedUser(int $tenantId): User
    {
        $user = User::factory()->forTenant($tenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        $this->optInUserToFederation($user->id);

        return $user;
    }

    private function protectRecipient(User $recipient, int $tenantId, bool $configureEnglandWales): void
    {
        if ($configureEnglandWales) {
            DB::table('tenant_safeguarding_settings')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'jurisdiction' => 'england_wales',
                    'policy_version' => 'safeguarded-contact-v1:federation-test',
                    'configured_by' => null,
                    'configured_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            app(SafeguardingJurisdictionService::class)->forget($tenantId);
        }

        $optionId = (int) DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id' => $tenantId,
            'option_key' => 'federation_protected_' . uniqid(),
            'option_type' => 'checkbox',
            'label' => 'Protected contact test',
            'description' => 'Protected contact test',
            'sort_order' => 0,
            'is_active' => 1,
            'is_required' => 0,
            'triggers' => json_encode([
                'requires_vetted_interaction' => true,
                'vetting_type_required' => 'dbs_enhanced',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $recipient->id,
            'option_id' => $optionId,
            'selected_value' => '1',
            'consent_given_at' => now(),
            'consent_ip' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SafeguardingTriggerService::invalidateCache($recipient->id, $tenantId);
    }
}
