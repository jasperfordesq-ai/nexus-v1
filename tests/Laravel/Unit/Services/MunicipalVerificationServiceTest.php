<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MunicipalVerificationService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MunicipalVerificationServiceTest
 *
 * Tests DNS-TXT and admin-attestation verification workflows including
 * status transitions (pending → verified → revoked), eligibility guards,
 * domain normalisation, and the isVerified helper.
 *
 * Fixture strategy: insertOrIgnore / insertGetId directly into
 * `municipal_verifications` for full control; DatabaseTransactions rolls
 * back after each test so rows never leak across tests.
 */
class MunicipalVerificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const ADMIN_ID  = 1;
    private const DOMAIN    = 'test-municipal.example.ie';

    private MunicipalVerificationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new MunicipalVerificationService();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Insert a raw verification row and return its id.
     */
    private function insertVerification(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'         => self::TENANT_ID,
            'domain'            => self::DOMAIN,
            'method'            => 'dns_txt',
            'status'            => 'pending',
            'dns_record_name'   => '_nexus-municipal.' . self::DOMAIN,
            'dns_record_value'  => 'nexus-municipal-verify=abc123',
            'requested_by'      => self::ADMIN_ID,
            'verified_by'       => null,
            'verified_at'       => null,
            'revoked_at'        => null,
            'attestation_note'  => null,
            'metadata'          => json_encode(['instructions_key' => 'municipal_verification_dns_txt']),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];

        return DB::table('municipal_verifications')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── startDnsVerification ─────────────────────────────────────────────────

    public function test_startDnsVerification_creates_pending_row(): void
    {
        $result = $this->svc->startDnsVerification(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN);

        $this->assertSame('pending', $result['status']);
        $this->assertSame('dns_txt', $result['method']);
        $this->assertSame(self::DOMAIN, $result['domain']);
        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
        $this->assertSame(self::ADMIN_ID, $result['requested_by']);
        $this->assertNotEmpty($result['dns_record_name']);
        $this->assertStringStartsWith('nexus-municipal-verify=', (string) $result['dns_record_value']);
    }

    public function test_startDnsVerification_dns_record_name_contains_domain(): void
    {
        $result = $this->svc->startDnsVerification(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN);

        $this->assertStringContainsString(self::DOMAIN, (string) $result['dns_record_name']);
        $this->assertStringStartsWith('_nexus-municipal.', (string) $result['dns_record_name']);
    }

    public function test_startDnsVerification_normalises_domain_with_https_prefix(): void
    {
        $result = $this->svc->startDnsVerification(
            self::TENANT_ID,
            self::ADMIN_ID,
            'https://example.ie/path/page'
        );

        $this->assertSame('example.ie', $result['domain']);
    }

    public function test_startDnsVerification_upserts_on_duplicate_domain(): void
    {
        // Call twice — second call should overwrite, not throw a unique-constraint error.
        $first  = $this->svc->startDnsVerification(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN);
        $second = $this->svc->startDnsVerification(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN);

        // Token is freshly generated each time.
        $this->assertNotSame($first['dns_record_value'], $second['dns_record_value']);

        // Still only one row in the table for this tenant+domain.
        $count = DB::table('municipal_verifications')
            ->where('tenant_id', self::TENANT_ID)
            ->where('domain', self::DOMAIN)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_startDnsVerification_throws_on_invalid_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc->startDnsVerification(self::TENANT_ID, self::ADMIN_ID, 'not a domain!!');
    }

    // ── attest ────────────────────────────────────────────────────────────────

    public function test_attest_sets_status_to_verified(): void
    {
        $result = $this->svc->attest(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN, 'Official council');

        $this->assertSame('verified', $result['status']);
        $this->assertSame('admin_attestation', $result['method']);
        $this->assertSame(self::ADMIN_ID, $result['verified_by']);
        $this->assertNotNull($result['verified_at']);
        $this->assertNull($result['dns_record_name']);
        $this->assertNull($result['dns_record_value']);
    }

    public function test_attest_stores_attestation_note(): void
    {
        $note   = 'Verified by council resolution 2026-05';
        $result = $this->svc->attest(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN, $note);

        $this->assertSame($note, $result['attestation_note']);
    }

    public function test_attest_null_note_stores_null(): void
    {
        $result = $this->svc->attest(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN, null);

        $this->assertNull($result['attestation_note']);
    }

    public function test_attest_whitespace_only_note_stores_null(): void
    {
        $result = $this->svc->attest(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN, '   ');

        $this->assertNull($result['attestation_note']);
    }

    // ── revoke ────────────────────────────────────────────────────────────────

    public function test_revoke_sets_status_to_revoked_and_returns_true(): void
    {
        $id = $this->insertVerification(['status' => 'verified']);

        $result = $this->svc->revoke(self::TENANT_ID, $id);

        $this->assertTrue($result);

        $row = DB::table('municipal_verifications')->where('id', $id)->first();
        $this->assertSame('revoked', $row->status);
        $this->assertNotNull($row->revoked_at);
    }

    public function test_revoke_returns_false_for_unknown_id(): void
    {
        $result = $this->svc->revoke(self::TENANT_ID, 9999999);

        $this->assertFalse($result);
    }

    public function test_revoke_is_tenant_scoped(): void
    {
        // Insert under a different tenant; revoke with our tenant ID should fail.
        $id = $this->insertVerification(['tenant_id' => 9997, 'domain' => 'other.example.ie']);

        $result = $this->svc->revoke(self::TENANT_ID, $id);

        $this->assertFalse($result);

        // Row in other tenant still 'pending'.
        $row = DB::table('municipal_verifications')->where('id', $id)->first();
        $this->assertSame('pending', $row->status);
    }

    // ── current ───────────────────────────────────────────────────────────────

    public function test_current_returns_verified_false_when_no_rows(): void
    {
        $result = $this->svc->current(self::TENANT_ID);

        $this->assertFalse($result['verified']);
        $this->assertNull($result['active']);
        $this->assertIsArray($result['items']);
    }

    public function test_current_returns_verified_true_with_active_row_when_one_verified(): void
    {
        $this->insertVerification(['status' => 'verified', 'verified_by' => self::ADMIN_ID, 'verified_at' => now()]);

        $result = $this->svc->current(self::TENANT_ID);

        $this->assertTrue($result['verified']);
        $this->assertNotNull($result['active']);
        $this->assertSame('verified', $result['active']['status']);
    }

    public function test_current_items_ordered_verified_first(): void
    {
        // Insert revoked first, then verified — ordering should put verified first.
        $this->insertVerification(['status' => 'revoked', 'domain' => 'revoked.example.ie']);
        $this->insertVerification(['status' => 'verified', 'domain' => 'verified.example.ie',
            'verified_by' => self::ADMIN_ID, 'verified_at' => now()]);

        $result = $this->svc->current(self::TENANT_ID);

        $this->assertTrue($result['verified']);
        $this->assertSame('verified', $result['items'][0]['status']);
    }

    // ── isVerified ────────────────────────────────────────────────────────────

    public function test_isVerified_returns_false_when_no_verified_row(): void
    {
        $this->insertVerification(['status' => 'pending']);

        $this->assertFalse($this->svc->isVerified(self::TENANT_ID));
    }

    public function test_isVerified_returns_true_when_verified_row_exists(): void
    {
        $this->insertVerification([
            'status'      => 'verified',
            'method'      => 'admin_attestation',
            'verified_by' => self::ADMIN_ID,
            'verified_at' => now(),
        ]);

        $this->assertTrue($this->svc->isVerified(self::TENANT_ID));
    }

    public function test_isVerified_is_tenant_scoped(): void
    {
        // Verified row belongs to a different tenant.
        $this->insertVerification([
            'tenant_id'   => 9998,
            'domain'      => 'other-tenant.example.ie',
            'status'      => 'verified',
            'verified_at' => now(),
        ]);

        $this->assertFalse($this->svc->isVerified(self::TENANT_ID));
    }

    // ── format / returned shape ───────────────────────────────────────────────

    public function test_attest_result_has_expected_keys(): void
    {
        $result = $this->svc->attest(self::TENANT_ID, self::ADMIN_ID, self::DOMAIN);

        foreach (['id', 'tenant_id', 'domain', 'method', 'status', 'dns_record_name',
                  'dns_record_value', 'requested_by', 'verified_by', 'verified_at',
                  'revoked_at', 'attestation_note', 'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $result, "Expected key '{$key}' missing from result.");
        }
    }
}
