<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Models\TenantSafeguardingOption;
use App\Services\SafeguardingTriggerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminSafeguardingOptionsController.
 *
 * Covers:
 *  - GET    /v2/admin/safeguarding/options        list options (admin)
 *  - POST   /v2/admin/safeguarding/options        create option (validation)
 *  - PUT    /v2/admin/safeguarding/options/{id}   update option
 *  - DELETE /v2/admin/safeguarding/options/{id}   deactivate option
 */
class AdminSafeguardingOptionsControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/safeguarding/options');

        $response->assertStatus(401);
    }

    public function test_index_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/safeguarding/options');

        $response->assertStatus(403);
    }

    public function test_store_requires_option_key(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'label' => 'Some label',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_label(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'option_key' => 'police_check',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_invalid_option_type(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'option_key' => 'police_check',
            'label' => 'Police check',
            'option_type' => 'not_a_valid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_returns_created_for_valid_option(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/safeguarding/options', [
            'option_key' => 'needs_broker_check_' . uniqid(),
            'label' => 'Needs broker check',
            'option_type' => 'checkbox',
            'triggers' => [
                'requires_broker_approval' => true,
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_update_rejects_invalid_trigger_keys(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $option = TenantSafeguardingOption::create([
            'tenant_id' => $this->testTenantId,
            'option_key' => 'needs_support_' . uniqid(),
            'option_type' => 'checkbox',
            'label' => 'Needs support',
            'sort_order' => 10,
            'is_active' => true,
            'is_required' => false,
            'triggers' => [],
        ]);

        $response = $this->apiPut("/v2/admin/safeguarding/options/{$option->id}", [
            'triggers' => [
                'unsafe_custom_trigger' => true,
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_update_rejects_non_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/safeguarding/options/1', [
            'label' => 'Hijack',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_cannot_turn_off_a_live_vetted_contact_trigger(): void
    {
        [$admin, $sender, $recipient, $option, $preferenceId] = $this->seedLivePresetProtection();

        Sanctum::actingAs($admin);
        $this->apiPut("/v2/admin/safeguarding/options/{$option->id}", [
            'triggers' => [
                'requires_vetted_interaction' => false,
                'restricts_matching' => true,
                'notify_admin_on_selection' => true,
                'vetting_type_required' => 'dbs_enhanced',
            ],
        ])->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');

        $option->refresh();
        $this->assertTrue($option->getTrigger('requires_vetted_interaction'));
        $this->assertNull(DB::table('user_safeguarding_preferences')
            ->where('id', $preferenceId)
            ->value('revoked_at'));
        $this->assertContactRemainsBlocked($sender, $recipient, 'Trigger removal must not open contact');
    }

    public function test_update_cannot_deactivate_an_option_with_a_live_protected_selection(): void
    {
        [$admin, $sender, $recipient, $option, $preferenceId] = $this->seedLivePresetProtection();

        Sanctum::actingAs($admin);
        $this->apiPut("/v2/admin/safeguarding/options/{$option->id}", [
            'is_active' => false,
        ])->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');

        $option->refresh();
        $this->assertTrue($option->is_active);
        $this->assertNull(DB::table('user_safeguarding_preferences')
            ->where('id', $preferenceId)
            ->value('revoked_at'));
        $this->assertContactRemainsBlocked($sender, $recipient, 'Deactivation must not open contact');
    }

    public function test_delete_cannot_revoke_a_live_protected_selection(): void
    {
        [$admin, $sender, $recipient, $option, $preferenceId] = $this->seedLivePresetProtection();

        Sanctum::actingAs($admin);
        $this->apiDelete("/v2/admin/safeguarding/options/{$option->id}")
            ->assertStatus(503)
            ->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');

        $option->refresh();
        $this->assertTrue($option->is_active);
        $this->assertNull(DB::table('user_safeguarding_preferences')
            ->where('id', $preferenceId)
            ->value('revoked_at'));
        $this->assertContactRemainsBlocked($sender, $recipient, 'Delete must not open contact');
    }

    /** @return array{User, User, User, TenantSafeguardingOption, int} */
    private function seedLivePresetProtection(): array
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        Sanctum::actingAs($admin);
        $this->apiPut('/v2/admin/vetting/policy', [
            'jurisdiction' => 'england_wales',
        ])->assertStatus(200);

        $option = TenantSafeguardingOption::withoutGlobalScopes()
            ->where('tenant_id', $this->testTenantId)
            ->where('option_key', 'requires_vetted_partners')
            ->where('preset_source', 'england_wales')
            ->where('is_active', true)
            ->firstOrFail();
        $preferenceId = (int) DB::table('user_safeguarding_preferences')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $recipient->id,
            'option_id' => $option->id,
            'selected_value' => '1',
            'consent_given_at' => now(),
            'consent_ip' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SafeguardingTriggerService::invalidateCache($recipient->id, $this->testTenantId);

        return [$admin, $sender, $recipient, $option, $preferenceId];
    }

    private function assertContactRemainsBlocked(User $sender, User $recipient, string $body): void
    {
        SafeguardingTriggerService::invalidateCache($recipient->id, $this->testTenantId);
        Sanctum::actingAs($sender);
        $this->apiPost('/v2/messages', [
            'recipient_id' => $recipient->id,
            'body' => $body,
        ])->assertStatus(403)->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('messages', [
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => $body,
        ]);
    }
}
