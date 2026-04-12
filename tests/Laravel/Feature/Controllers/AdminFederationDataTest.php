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

    public function test_export_produces_valid_parseable_json_with_expected_keys(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/data/export');
        $response->assertStatus(200);

        $body = $response->streamedContent();
        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded, 'Streamed export must be valid JSON');
        $this->assertArrayHasKey('meta', $decoded);
        $this->assertArrayHasKey('partnerships', $decoded);
        $this->assertArrayHasKey('external_partners', $decoded);
        $this->assertArrayHasKey('reputation', $decoded);
        $this->assertArrayHasKey('api_logs', $decoded);
        $this->assertSame($this->testTenantId, $decoded['meta']['tenant_id']);
        $this->assertSame(1, $decoded['meta']['format_version']);
        $this->assertIsArray($decoded['partnerships']);
        $this->assertIsArray($decoded['external_partners']);
        $this->assertIsArray($decoded['reputation']);
        $this->assertIsArray($decoded['api_logs']);
    }

    public function test_export_streams_large_api_logs_without_memory_spike(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $keyId = DB::table('federation_api_keys')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Stream Test Key',
            'key_prefix' => 'fed_stream',
            'key_hash' => str_repeat('x', 64),
            'created_at' => now(),
        ]);

        // Seed 10,000 recent api_log rows in batched inserts (keep seed memory low).
        $now = now();
        $batch = [];
        $target = 10000;
        for ($i = 0; $i < $target; $i++) {
            $batch[] = [
                'api_key_id' => $keyId,
                'endpoint' => '/stream/' . $i,
                'method' => 'GET',
                'ip_address' => '127.0.0.1',
                'signature_valid' => 1,
                'response_code' => 200,
                'response_time_ms' => 5,
                'created_at' => $now->copy()->subMinutes($i % 1000),
            ];
            if (count($batch) >= 500) {
                DB::table('federation_api_logs')->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            DB::table('federation_api_logs')->insert($batch);
        }

        // Reset memory tracking before the measured section.
        gc_collect_cycles();
        $beforePeak = memory_get_peak_usage(true);

        // Sanity: seeded rows should be visible pre-request.
        $seededCount = DB::table('federation_api_logs')->where('api_key_id', $keyId)->count();
        $this->assertSame($target, $seededCount, 'Seed did not commit rows');

        $response = $this->apiPost('/v2/admin/federation/data/export');
        $response->assertStatus(200);

        $body = $response->streamedContent();
        $peakAfter = memory_get_peak_usage(true);

        // Validate the streamed body is parseable JSON and contains all seeded rows.
        $decoded = json_decode($body, true);
        $this->assertIsArray($decoded, 'Streamed 10k-row export must be valid JSON');
        $this->assertArrayHasKey('api_logs', $decoded);
        $this->assertGreaterThanOrEqual($target, count($decoded['api_logs']));

        // Memory growth during the streamed export should stay modest. The body
        // string itself is buffered by streamedContent() in tests so we compare
        // the delta against a generous ceiling — with array-based (non-chunked)
        // loading of 10k rows + json_encode this would easily exceed 64MB once
        // scaled; chunked cursor streaming keeps the DB-side cost bounded.
        $deltaBytes = $peakAfter - $beforePeak;
        $this->assertLessThan(
            64 * 1024 * 1024,
            $deltaBytes,
            'Streamed export should not spike memory above 64MB delta; got ' . $deltaBytes . ' bytes'
        );
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
}
