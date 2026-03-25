<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Integration tests for the safeguarding preference -> trigger -> restriction flow.
 *
 * Verifies that saving safeguarding preferences during onboarding correctly:
 * - Activates messaging restrictions via SafeguardingTriggerService
 * - Records GDPR consent (timestamp + IP)
 * - Creates audit log entries
 * - Strips internal trigger data from member-facing API responses
 * - Clears restrictions when preferences are revoked
 */
class SafeguardingIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create an authenticated member user.
     */
    private function authenticatedMember(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Create a safeguarding option with the given triggers.
     *
     * @param array $triggers Trigger configuration (e.g. requires_broker_approval, restricts_messaging)
     * @return int The option ID
     */
    private function createSafeguardingOption(array $triggers = [], string $optionKey = 'test_option'): int
    {
        return DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'option_key' => $optionKey . '_' . uniqid(),
            'option_type' => 'checkbox',
            'label' => 'Test Safeguarding Option',
            'description' => 'A test safeguarding option for integration testing',
            'is_active' => 1,
            'is_required' => 0,
            'sort_order' => 0,
            'triggers' => json_encode($triggers),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // Test 1: Preference save activates messaging restriction
    // =========================================================================

    public function test_preference_save_activates_messaging_restriction(): void
    {
        $user = $this->authenticatedMember();

        $optionId = $this->createSafeguardingOption([
            'requires_broker_approval' => true,
            'restricts_messaging' => true,
        ]);

        $response = $this->apiPost('/v2/onboarding/safeguarding', [
            'preferences' => [
                ['option_id' => $optionId, 'value' => '1'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.message', 'Safeguarding preferences saved');

        // Verify user_messaging_restrictions row was created
        $restriction = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($restriction, 'Messaging restriction row should be created');
        $this->assertEquals(1, (int) $restriction->under_monitoring, 'under_monitoring should be 1');
        $this->assertEquals(1, (int) $restriction->requires_broker_approval, 'requires_broker_approval should be 1');
    }

    // =========================================================================
    // Test 2: Preference revocation clears restrictions
    // =========================================================================

    public function test_preference_revocation_clears_restrictions(): void
    {
        $user = $this->authenticatedMember();

        $optionId = $this->createSafeguardingOption([
            'requires_broker_approval' => true,
            'restricts_messaging' => true,
        ]);

        // Save preferences first
        $response = $this->apiPost('/v2/onboarding/safeguarding', [
            'preferences' => [
                ['option_id' => $optionId, 'value' => '1'],
            ],
        ]);
        $response->assertStatus(200);

        // Verify restriction exists
        $restriction = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($restriction, 'Restriction should exist after saving preferences');
        $this->assertEquals(1, (int) $restriction->under_monitoring);

        // Revoke the preference via the service directly
        // (The revoke endpoint calls SafeguardingPreferenceService::revokePreference)
        \App\Services\SafeguardingPreferenceService::revokePreference($user->id, $optionId);

        // Verify restrictions are cleared
        $restriction = DB::table('user_messaging_restrictions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->first();

        // Restrictions should be cleared (under_monitoring=0) since the only triggering preference was revoked
        if ($restriction) {
            $this->assertEquals(0, (int) $restriction->under_monitoring, 'under_monitoring should be cleared after revocation');
            $this->assertEquals(0, (int) $restriction->requires_broker_approval, 'requires_broker_approval should be cleared after revocation');
        }
        // If no row exists at all, that's also acceptable (restriction fully removed)
    }

    // =========================================================================
    // Test 3: Safeguarding options strips triggers from response
    // =========================================================================

    public function test_safeguarding_options_strips_triggers_from_response(): void
    {
        $this->authenticatedMember();

        // Create an option with triggers that should NOT be visible to members
        $this->createSafeguardingOption([
            'requires_broker_approval' => true,
            'restricts_messaging' => true,
            'notify_admin_on_selection' => true,
        ], 'visible_option');

        $response = $this->apiGet('/v2/onboarding/safeguarding-options');

        $response->assertStatus(200);

        $body = $response->getContent();

        // The 'triggers' key should NOT appear in the member-facing response
        $this->assertStringNotContainsString('"triggers"', $body, 'Triggers should be stripped from member-facing safeguarding options response');
        $this->assertStringNotContainsString('requires_broker_approval', $body, 'Trigger details should not leak to members');
        $this->assertStringNotContainsString('restricts_messaging', $body, 'Trigger details should not leak to members');
        $this->assertStringNotContainsString('notify_admin_on_selection', $body, 'Trigger details should not leak to members');
    }

    // =========================================================================
    // Test 4: Preference save records GDPR consent
    // =========================================================================

    public function test_preference_save_records_gdpr_consent(): void
    {
        $user = $this->authenticatedMember();

        $optionId = $this->createSafeguardingOption([], 'consent_test');

        $response = $this->apiPost('/v2/onboarding/safeguarding', [
            'preferences' => [
                ['option_id' => $optionId, 'value' => '1'],
            ],
        ]);

        $response->assertStatus(200);

        // Verify GDPR consent fields are populated
        $pref = DB::table('user_safeguarding_preferences')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('option_id', $optionId)
            ->first();

        $this->assertNotNull($pref, 'Preference row should exist');
        $this->assertNotNull($pref->consent_given_at, 'consent_given_at should be set');
        $this->assertNotNull($pref->consent_ip, 'consent_ip should be set');
    }

    // =========================================================================
    // Test 5: Preference save creates audit log
    // =========================================================================

    public function test_preference_save_creates_audit_log(): void
    {
        $user = $this->authenticatedMember();

        $optionId = $this->createSafeguardingOption([], 'audit_test');

        // Clear any pre-existing audit logs for this action
        DB::table('activity_log')
            ->where('action', 'safeguarding_preferences_updated')
            ->where('user_id', $user->id)
            ->delete();

        $response = $this->apiPost('/v2/onboarding/safeguarding', [
            'preferences' => [
                ['option_id' => $optionId, 'value' => '1'],
            ],
        ]);

        $response->assertStatus(200);

        // Verify audit log entry was created
        $log = DB::table('activity_log')
            ->where('action', 'safeguarding_preferences_updated')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($log, 'Audit log entry should be created for safeguarding preference save');
        $this->assertEquals('safeguarding', $log->action_type);
        $this->assertEquals('user', $log->entity_type);
        $this->assertEquals($user->id, (int) $log->entity_id);
    }
}
