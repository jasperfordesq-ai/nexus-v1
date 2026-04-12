<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminFederationDataController.
 *
 * Verifies:
 * - export endpoint requires admin, returns JSON stream, and redacts secrets
 * - purge endpoint respects the `days` parameter and rejects out-of-range values
 */
class AdminFederationDataTest extends TestCase
{
    use DatabaseTransactions;

    // ─── Export ──────────────────────────────────────────────────────────────

    public function test_export_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/federation/data/export');
        $response->assertStatus(403);
    }

    public function test_export_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/federation/data/export');
        $response->assertStatus(401);
    }

    public function test_export_redacts_external_partner_secrets(): void
    {
        if (!$this->tableExists('federation_external_partners')) {
            $this->markTestSkipped('federation_external_partners table not present in test DB');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        DB::table('federation_external_partners')->insert([
            'tenant_id' => $this->testTenantId,
            'name' => 'Secret Partner',
            'base_url' => 'https://partner.test/api',
            'api_key' => 'SECRET_API_KEY_VALUE',
            'signing_secret' => 'SECRET_SIGNING_VALUE',
            'oauth_client_secret' => 'SECRET_OAUTH_VALUE',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $response = $this->apiPost('/v2/admin/federation/data/export');
        $response->assertStatus(200);

        // Streamed responses must be captured via streamedContent()
        $body = $response->streamedContent();
        $this->assertStringNotContainsString('SECRET_API_KEY_VALUE', $body);
        $this->assertStringNotContainsString('SECRET_SIGNING_VALUE', $body);
        $this->assertStringNotContainsString('SECRET_OAUTH_VALUE', $body);
        $this->assertStringContainsString('Secret Partner', $body);
    }

    // ─── Purge ───────────────────────────────────────────────────────────────

    public function test_purge_rejects_days_out_of_range(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/data/purge', ['days' => 1]);
        $response->assertStatus(422);

        $response = $this->apiPost('/v2/admin/federation/data/purge', ['days' => 99999]);
        $response->assertStatus(422);
    }

    public function test_purge_respects_days_param(): void
    {
        if (!$this->tableExists('federation_api_logs') || !$this->tableExists('federation_api_keys')) {
            $this->markTestSkipped('federation_api_logs / federation_api_keys tables not present in test DB');
        }

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $keyId = DB::table('federation_api_keys')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Test Key',
            'key_prefix' => 'fed_test',
            'key_hash' => str_repeat('x', 64),
            'created_at' => now(),
        ]);

        // Old log (400 days ago) — should be purged at days=365
        DB::table('federation_api_logs')->insert([
            'api_key_id' => $keyId,
            'endpoint' => '/old',
            'method' => 'GET',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(400),
        ]);
        // Recent log (10 days ago) — should remain
        DB::table('federation_api_logs')->insert([
            'api_key_id' => $keyId,
            'endpoint' => '/recent',
            'method' => 'GET',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->apiPost('/v2/admin/federation/data/purge', ['days' => 365]);
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['deleted']);
        $this->assertSame(365, $data['days']);

        $remaining = DB::table('federation_api_logs')
            ->where('api_key_id', $keyId)
            ->count();
        $this->assertSame(1, $remaining);
    }

    public function test_purge_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/federation/data/purge', ['days' => 365]);
        $response->assertStatus(403);
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
