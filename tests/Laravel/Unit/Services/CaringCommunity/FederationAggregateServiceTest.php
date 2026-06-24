<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\FederationAggregateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * FederationAggregateServiceTest
 *
 * Covers:
 *  - bucketMemberCount: all four brackets
 *  - signPayload / canonicalJson: deterministic signature, key-order-independent
 *  - generateSecret: length and hex format
 *  - rotateSecret: persists new secret to federation_aggregate_consents
 *  - setEnabled: inserts consent row with secret on first enable; does not overwrite existing secret on re-enable
 *  - getConsent: returns null when missing, correct shape when present
 *  - getConsentInternal: returns raw row
 *  - logQuery / recentAuditLog: persists and retrieves entries, signature snippet truncated
 *  - pruneOldLogs: deletes old records, leaves recent ones
 *  - compute: returns correct structure and respects period filters
 */
class FederationAggregateServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private FederationAggregateService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new FederationAggregateService();
    }

    // ── bucketMemberCount ──────────────────────────────────────────────────────

    public function test_bucketMemberCount_returns_under_50_bracket(): void
    {
        $this->assertSame('<50', $this->svc->bucketMemberCount(0));
        $this->assertSame('<50', $this->svc->bucketMemberCount(1));
        $this->assertSame('<50', $this->svc->bucketMemberCount(49));
    }

    public function test_bucketMemberCount_returns_50_to_200_bracket(): void
    {
        $this->assertSame('50-200', $this->svc->bucketMemberCount(50));
        $this->assertSame('50-200', $this->svc->bucketMemberCount(199));
    }

    public function test_bucketMemberCount_returns_200_to_1000_bracket(): void
    {
        $this->assertSame('200-1000', $this->svc->bucketMemberCount(200));
        $this->assertSame('200-1000', $this->svc->bucketMemberCount(999));
    }

    public function test_bucketMemberCount_returns_over_1000_bracket(): void
    {
        $this->assertSame('>1000', $this->svc->bucketMemberCount(1000));
        $this->assertSame('>1000', $this->svc->bucketMemberCount(99999));
    }

    // ── generateSecret ─────────────────────────────────────────────────────────

    public function test_generateSecret_returns_64_char_hex_string(): void
    {
        $secret = $this->svc->generateSecret();
        $this->assertSame(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
    }

    public function test_generateSecret_returns_different_values_on_successive_calls(): void
    {
        $s1 = $this->svc->generateSecret();
        $s2 = $this->svc->generateSecret();
        $this->assertNotSame($s1, $s2);
    }

    // ── signPayload / canonicalJson ────────────────────────────────────────────

    public function test_signPayload_is_deterministic_for_same_input(): void
    {
        $payload = ['b' => 2, 'a' => 1, 'c' => [3, 1, 2]];
        $secret  = 'test-secret-abc';

        $sig1 = $this->svc->signPayload($payload, $secret);
        $sig2 = $this->svc->signPayload($payload, $secret);

        $this->assertSame($sig1, $sig2);
        $this->assertSame(64, strlen($sig1)); // HMAC-SHA256 = 32 bytes = 64 hex chars
    }

    public function test_signPayload_is_key_order_independent(): void
    {
        // Arrays with same keys but different insertion order must produce the same signature.
        $payloadA = ['z' => 'last', 'a' => 'first'];
        $payloadB = ['a' => 'first', 'z' => 'last'];
        $secret   = 'key-order-test';

        $this->assertSame(
            $this->svc->signPayload($payloadA, $secret),
            $this->svc->signPayload($payloadB, $secret)
        );
    }

    public function test_signPayload_differs_with_different_secrets(): void
    {
        $payload = ['x' => 1];
        $sigA    = $this->svc->signPayload($payload, 'secret-A');
        $sigB    = $this->svc->signPayload($payload, 'secret-B');

        $this->assertNotSame($sigA, $sigB);
    }

    public function test_canonicalJson_sorts_keys_recursively(): void
    {
        $payload  = ['z' => ['b' => 2, 'a' => 1], 'a' => 0];
        $json     = $this->svc->canonicalJson($payload);
        $decoded  = json_decode($json, true);

        // Top-level keys must be sorted ascending
        $this->assertSame(['a', 'z'], array_keys($decoded));
        // Nested assoc keys also sorted
        $this->assertSame(['a', 'b'], array_keys($decoded['z']));
    }

    // ── rotateSecret ──────────────────────────────────────────────────────────

    public function test_rotateSecret_persists_new_secret_and_returns_it(): void
    {
        $secret = $this->svc->rotateSecret(self::TENANT_ID);

        $this->assertSame(64, strlen($secret));
        $row = DB::table('federation_aggregate_consents')
            ->where('tenant_id', self::TENANT_ID)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame($secret, $row->signing_secret);
    }

    public function test_rotateSecret_replaces_existing_secret(): void
    {
        $oldSecret = $this->svc->rotateSecret(self::TENANT_ID);
        $newSecret = $this->svc->rotateSecret(self::TENANT_ID);

        $this->assertNotSame($oldSecret, $newSecret);

        $row = DB::table('federation_aggregate_consents')
            ->where('tenant_id', self::TENANT_ID)
            ->first();
        $this->assertSame($newSecret, $row->signing_secret);
    }

    // ── setEnabled / getConsent ───────────────────────────────────────────────

    public function test_getConsent_returns_null_when_no_row_exists(): void
    {
        // Use a tenant ID unlikely to have existing data
        $result = $this->svc->getConsent(88887777);
        $this->assertNull($result);
    }

    public function test_setEnabled_creates_consent_row_with_secret_when_enabling(): void
    {
        $result = $this->svc->setEnabled(self::TENANT_ID, true);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['has_secret']);
        $this->assertNotNull($result['last_rotated_at']);
    }

    public function test_setEnabled_creates_consent_row_without_secret_when_disabling_first(): void
    {
        // Disable a tenant that has no existing row — should create row with enabled=false, no secret
        $highTenantId = 88886666;
        $result = $this->svc->setEnabled($highTenantId, false);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['has_secret']);
    }

    public function test_getConsent_returns_correct_shape_after_setEnabled(): void
    {
        $this->svc->setEnabled(self::TENANT_ID, true);
        $consent = $this->svc->getConsent(self::TENANT_ID);

        $this->assertNotNull($consent);
        $this->assertArrayHasKey('enabled', $consent);
        $this->assertArrayHasKey('has_secret', $consent);
        $this->assertArrayHasKey('last_rotated_at', $consent);
        $this->assertTrue($consent['enabled']);
        $this->assertTrue($consent['has_secret']);
    }

    public function test_setEnabled_does_not_overwrite_existing_secret_on_re_enable(): void
    {
        // First enable — sets secret
        $this->svc->setEnabled(self::TENANT_ID, true);
        $firstSecret = DB::table('federation_aggregate_consents')
            ->where('tenant_id', self::TENANT_ID)
            ->value('signing_secret');

        // Disable, then re-enable — existing secret must be preserved (not replaced)
        $this->svc->setEnabled(self::TENANT_ID, false);
        $this->svc->setEnabled(self::TENANT_ID, true);

        $secondSecret = DB::table('federation_aggregate_consents')
            ->where('tenant_id', self::TENANT_ID)
            ->value('signing_secret');

        $this->assertSame($firstSecret, $secondSecret, 'Existing secret must not be replaced on re-enable');
    }

    // ── getConsentInternal ────────────────────────────────────────────────────

    public function test_getConsentInternal_returns_raw_row_with_signing_secret(): void
    {
        $this->svc->setEnabled(self::TENANT_ID, true);
        $row = $this->svc->getConsentInternal(self::TENANT_ID);

        $this->assertNotNull($row);
        $this->assertObjectHasProperty('signing_secret', $row);
        $this->assertSame(64, strlen($row->signing_secret));
    }

    public function test_getConsentInternal_returns_null_when_no_row(): void
    {
        $this->assertNull($this->svc->getConsentInternal(77776666));
    }

    // ── logQuery / recentAuditLog ─────────────────────────────────────────────

    public function test_logQuery_inserts_entry_and_recentAuditLog_retrieves_it(): void
    {
        $signature = str_repeat('a', 64);
        $this->svc->logQuery(
            tenantId: self::TENANT_ID,
            requesterOrigin: 'https://peer.example.com',
            periodFrom: '2026-01-01',
            periodTo: '2026-03-31',
            fieldsReturned: ['hours', 'members'],
            signature: $signature
        );

        $log = $this->svc->recentAuditLog(self::TENANT_ID);

        $this->assertNotEmpty($log);
        $latest = $log[0];
        $this->assertSame('https://peer.example.com', $latest['requester_origin']);
        $this->assertSame('2026-01-01', $latest['period_from']);
        $this->assertSame('2026-03-31', $latest['period_to']);
        $this->assertIsArray($latest['fields_returned']);
        $this->assertContains('hours', $latest['fields_returned']);

        // Signature snippet: 16 chars + '…' (UTF-8 = 3 bytes) → 19 bytes total
        $this->assertStringEndsWith('…', $latest['signature_snippet']);
        // '…' is U+2026 HORIZONTAL ELLIPSIS, 3 bytes in UTF-8
        $this->assertSame(19, strlen($latest['signature_snippet']));
        $this->assertStringStartsWith(substr($signature, 0, 16), $latest['signature_snippet']);
    }

    public function test_recentAuditLog_returns_empty_for_tenant_with_no_entries(): void
    {
        $log = $this->svc->recentAuditLog(88885555);
        $this->assertIsArray($log);
        $this->assertSame([], $log);
    }

    // ── pruneOldLogs ──────────────────────────────────────────────────────────

    public function test_pruneOldLogs_deletes_entries_older_than_365_days(): void
    {
        $oldDate   = now()->subDays(366)->format('Y-m-d H:i:s');
        $recentDate = now()->subDays(10)->format('Y-m-d H:i:s');

        DB::table('federation_aggregate_query_log')->insert([
            'tenant_id'          => self::TENANT_ID,
            'requester_origin'   => 'https://old.example.com',
            'period_from'        => '2025-01-01',
            'period_to'          => '2025-03-31',
            'fields_returned'    => '["hours"]',
            'response_signature' => str_repeat('b', 64),
            'created_at'         => $oldDate,
        ]);

        $recentId = DB::table('federation_aggregate_query_log')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'requester_origin'   => 'https://recent.example.com',
            'period_from'        => '2026-04-01',
            'period_to'          => '2026-06-30',
            'fields_returned'    => '["hours"]',
            'response_signature' => str_repeat('c', 64),
            'created_at'         => $recentDate,
        ]);

        $deleted = $this->svc->pruneOldLogs();

        $this->assertGreaterThanOrEqual(1, $deleted);
        // Recent entry must still exist
        $this->assertTrue(
            DB::table('federation_aggregate_query_log')->where('id', $recentId)->exists()
        );
    }

    // ── compute ───────────────────────────────────────────────────────────────

    public function test_compute_returns_required_top_level_keys(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('hours', $result);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('partner_orgs', $result);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function test_compute_period_matches_supplied_arguments(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertSame('2026-01-01', $result['period']['from']);
        $this->assertSame('2026-06-30', $result['period']['to']);
    }

    public function test_compute_member_bracket_is_bucketed_string(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertContains(
            $result['members']['bracket'],
            ['<50', '50-200', '200-1000', '>1000'],
            'members.bracket must be one of the four allowed strings'
        );
    }

    public function test_compute_partner_orgs_count_is_non_negative_int(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertIsInt($result['partner_orgs']['count']);
        $this->assertGreaterThanOrEqual(0, $result['partner_orgs']['count']);
    }

    public function test_compute_hours_total_approved_reflects_approved_vol_logs(): void
    {
        // Use a narrow far-future period unlikely to have any pre-existing vol_logs.
        // DatabaseTransactions rolls all inserts back after the test.
        $periodFrom = '2099-01-01';
        $periodTo   = '2099-01-31';

        // Insert a user for FK requirement
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Test User ' . uniqid(),
            'email'      => 'fed-agg-' . uniqid() . '@test.example',
            'status'     => 'active',
            'role'       => 'member',
            'created_at' => now(),
        ]);

        DB::table('vol_logs')->insert([
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2099-01-10',
                'hours'       => 3.00,
                'status'      => 'approved',
                'created_at'  => now(),
            ],
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2099-01-20',
                'hours'       => 2.50,
                'status'      => 'approved',
                'created_at'  => now(),
            ],
            // Pending — should NOT contribute
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2099-01-15',
                'hours'       => 10.00,
                'status'      => 'pending',
                'created_at'  => now(),
            ],
            // Out-of-range approved — should NOT contribute
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2098-12-31',
                'hours'       => 5.00,
                'status'      => 'approved',
                'created_at'  => now(),
            ],
        ]);

        $result = $this->svc->compute($periodFrom, $periodTo);

        // Total approved in range must be exactly 5.50 (3.00 + 2.50)
        $this->assertSame(5.5, $result['hours']['total_approved']);
    }

    public function test_compute_by_month_sums_hours_per_month(): void
    {
        // Use a far-future month to avoid collisions with pre-existing data.
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Monthly Test ' . uniqid(),
            'email'      => 'monthly-' . uniqid() . '@test.example',
            'status'     => 'active',
            'role'       => 'member',
            'created_at' => now(),
        ]);

        DB::table('vol_logs')->insert([
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2099-02-10',
                'hours'       => 4.00,
                'status'      => 'approved',
                'created_at'  => now(),
            ],
            [
                'tenant_id'   => self::TENANT_ID,
                'user_id'     => $userId,
                'date_logged' => '2099-02-20',
                'hours'       => 2.00,
                'status'      => 'approved',
                'created_at'  => now(),
            ],
        ]);

        $result = $this->svc->compute('2099-02-01', '2099-02-28');

        $this->assertNotEmpty($result['hours']['by_month']);
        $feb = collect($result['hours']['by_month'])->firstWhere('month', '2099-02');
        $this->assertNotNull($feb, 'Expected a 2099-02 entry in by_month');
        $this->assertSame(6.0, $feb['hours']);
    }

    public function test_compute_generated_at_is_iso8601_utc(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $result['generated_at']
        );
    }

    public function test_compute_tenant_meta_matches_test_tenant(): void
    {
        $result = $this->svc->compute('2026-01-01', '2026-06-30');

        $this->assertSame('hour-timebank', $result['tenant']['slug']);
        $this->assertIsString($result['tenant']['name']);
    }
}
