<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\PartnerApi;

use App\Core\TenantContext;
use App\Services\PartnerApi\PartnerApiAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for PartnerApiAuthService (AG60).
 *
 * Strategy:
 *   - issueClientCredentials: assert format/prefix, DB row written, secret is bcrypt-hashed (not stored raw).
 *   - verifyClient: valid credentials → partner row; wrong secret → null; revoked cred → null;
 *                   inactive partner → null; last_used_at is updated on success.
 *   - revokeCredentials: sets revoked_at; subsequent verifyClient fails.
 *   - issueAccessToken: raw token prefixed `at_`, only SHA-256 hash stored in DB, correct TTL,
 *                       scopes filtered to allowed_scopes, wildcard scopes.
 *   - resolveAccessToken: valid token → partner+scopes; expired → null; revoked → null; wrong token → null.
 *   - revokeAccessToken / revokeAccessTokenForPartner: correct revocation behaviour.
 */
class PartnerApiAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertPartner(string $status = 'active', array $scopes = ['wallet:read', 'wallet:write']): int
    {
        return (int) DB::table('api_partners')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'name'           => 'Test Partner ' . uniqid(),
            'slug'           => 'tp-' . uniqid(),
            'status'         => $status,
            'is_sandbox'     => 1,
            'allowed_scopes' => json_encode($scopes),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Returns [client_id, client_secret, credential_row_id]. */
    private function issueAndFetch(int $partnerId): array
    {
        $creds = PartnerApiAuthService::issueClientCredentials($partnerId);
        $row = DB::table('api_partner_credentials')
            ->where('client_id', $creds['client_id'])
            ->first();
        return [$creds['client_id'], $creds['client_secret'], $row];
    }

    // ── issueClientCredentials ────────────────────────────────────────────────

    public function test_issueClientCredentials_returns_prefixed_client_id_and_secret(): void
    {
        $partnerId = $this->insertPartner();
        $creds = PartnerApiAuthService::issueClientCredentials($partnerId);

        $this->assertStringStartsWith('pk_', $creds['client_id']);
        $this->assertStringStartsWith('sk_', $creds['client_secret']);
    }

    public function test_issueClientCredentials_stores_bcrypt_hash_not_raw_secret(): void
    {
        $partnerId = $this->insertPartner();
        [, $rawSecret, $row] = $this->issueAndFetch($partnerId);

        $this->assertNotNull($row, 'Credential row should be inserted');
        // The stored hash must NOT equal the raw secret.
        $this->assertNotSame($rawSecret, $row->client_secret_hash);
        // But bcrypt verify must pass.
        $this->assertTrue(
            password_verify($rawSecret, $row->client_secret_hash),
            'Stored hash must be bcrypt-verifiable with the raw secret'
        );
    }

    public function test_issueClientCredentials_row_is_tenant_scoped(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId] = $this->issueAndFetch($partnerId);

        $row = DB::table('api_partner_credentials')->where('client_id', $clientId)->first();
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
    }

    // ── verifyClient ──────────────────────────────────────────────────────────

    public function test_verifyClient_returns_partner_row_on_valid_credentials(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId, $secret] = $this->issueAndFetch($partnerId);

        $partner = PartnerApiAuthService::verifyClient($clientId, $secret);

        $this->assertIsArray($partner);
        $this->assertSame($partnerId, (int) $partner['id']);
    }

    public function test_verifyClient_returns_null_for_wrong_secret(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId] = $this->issueAndFetch($partnerId);

        $result = PartnerApiAuthService::verifyClient($clientId, 'sk_wrongsecret');

        $this->assertNull($result);
    }

    public function test_verifyClient_returns_null_for_unknown_client_id(): void
    {
        $result = PartnerApiAuthService::verifyClient('pk_doesnotexist', 'sk_anything');

        $this->assertNull($result);
    }

    public function test_verifyClient_returns_null_for_revoked_credentials(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId, $secret] = $this->issueAndFetch($partnerId);

        PartnerApiAuthService::revokeCredentials($partnerId);

        $result = PartnerApiAuthService::verifyClient($clientId, $secret);
        $this->assertNull($result);
    }

    public function test_verifyClient_returns_null_when_partner_is_not_active(): void
    {
        $partnerId = $this->insertPartner(status: 'suspended');
        [$clientId, $secret] = $this->issueAndFetch($partnerId);

        $result = PartnerApiAuthService::verifyClient($clientId, $secret);

        $this->assertNull($result);
    }

    public function test_verifyClient_updates_last_used_at_on_success(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId, $secret, $row] = $this->issueAndFetch($partnerId);

        $this->assertNull($row->last_used_at, 'last_used_at should start null');

        PartnerApiAuthService::verifyClient($clientId, $secret);

        $updated = DB::table('api_partner_credentials')->where('client_id', $clientId)->first();
        $this->assertNotNull($updated->last_used_at, 'last_used_at must be set after successful verify');
    }

    // ── revokeCredentials ─────────────────────────────────────────────────────

    public function test_revokeCredentials_sets_revoked_at_on_credentials(): void
    {
        $partnerId = $this->insertPartner();
        [$clientId] = $this->issueAndFetch($partnerId);

        $count = PartnerApiAuthService::revokeCredentials($partnerId);

        $this->assertSame(1, $count);
        $row = DB::table('api_partner_credentials')->where('client_id', $clientId)->first();
        $this->assertNotNull($row->revoked_at);
    }

    // ── issueAccessToken ──────────────────────────────────────────────────────

    public function test_issueAccessToken_returns_bearer_token_with_correct_format(): void
    {
        $partnerId = $this->insertPartner();
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();

        $result = PartnerApiAuthService::issueAccessToken($partnerRow);

        $this->assertStringStartsWith('at_', $result['access_token']);
        $this->assertSame('bearer', $result['token_type']);
        $this->assertSame(PartnerApiAuthService::DEFAULT_TOKEN_TTL_SECONDS, $result['expires_in']);
    }

    public function test_issueAccessToken_stores_only_sha256_hash_not_raw_token(): void
    {
        $partnerId = $this->insertPartner();
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();

        $result = PartnerApiAuthService::issueAccessToken($partnerRow);
        $rawToken = $result['access_token'];
        $expectedHash = hash('sha256', $rawToken);

        $dbRow = DB::table('api_oauth_tokens')->where('access_token_hash', $expectedHash)->first();

        $this->assertNotNull($dbRow, 'Token row must exist in api_oauth_tokens');
        // Raw token must NOT be stored anywhere in the row.
        $this->assertStringNotContainsString($rawToken, $dbRow->access_token_hash);
        $this->assertSame($expectedHash, $dbRow->access_token_hash);
    }

    public function test_issueAccessToken_filters_requested_scopes_to_allowed_scopes(): void
    {
        $partnerId = $this->insertPartner(scopes: ['wallet:read', 'wallet:write']);
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();

        // Request a disallowed scope mixed with an allowed one.
        $result = PartnerApiAuthService::issueAccessToken($partnerRow, ['wallet:read', 'admin:full']);

        $this->assertSame('wallet:read', $result['scope']);
    }

    public function test_issueAccessToken_uses_all_allowed_scopes_when_none_requested(): void
    {
        $partnerId = $this->insertPartner(scopes: ['wallet:read', 'listings:read']);
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();

        $result = PartnerApiAuthService::issueAccessToken($partnerRow, null);

        $this->assertStringContainsString('wallet:read', $result['scope']);
        $this->assertStringContainsString('listings:read', $result['scope']);
    }

    // ── resolveAccessToken ────────────────────────────────────────────────────

    public function test_resolveAccessToken_returns_partner_and_scopes_for_valid_token(): void
    {
        $partnerId = $this->insertPartner(scopes: ['wallet:read']);
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();
        $issued = PartnerApiAuthService::issueAccessToken($partnerRow);

        $resolved = PartnerApiAuthService::resolveAccessToken($issued['access_token']);

        $this->assertIsArray($resolved);
        $this->assertSame($partnerId, (int) $resolved['partner']['id']);
        $this->assertContains('wallet:read', $resolved['scopes']);
    }

    public function test_resolveAccessToken_returns_null_for_unknown_token(): void
    {
        $result = PartnerApiAuthService::resolveAccessToken('at_doesnotexist');

        $this->assertNull($result);
    }

    public function test_resolveAccessToken_returns_null_for_revoked_token(): void
    {
        $partnerId = $this->insertPartner();
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();
        $issued = PartnerApiAuthService::issueAccessToken($partnerRow);

        PartnerApiAuthService::revokeAccessToken($issued['access_token']);

        $result = PartnerApiAuthService::resolveAccessToken($issued['access_token']);
        $this->assertNull($result);
    }

    public function test_resolveAccessToken_returns_null_for_expired_token(): void
    {
        $partnerId = $this->insertPartner();
        $partnerRow = (array) DB::table('api_partners')->where('id', $partnerId)->first();
        $issued = PartnerApiAuthService::issueAccessToken($partnerRow);
        $hash = hash('sha256', $issued['access_token']);

        // Backdate the expiry to the past.
        DB::table('api_oauth_tokens')
            ->where('access_token_hash', $hash)
            ->update(['expires_at' => now()->subMinutes(5)]);

        $result = PartnerApiAuthService::resolveAccessToken($issued['access_token']);
        $this->assertNull($result);
    }

    // ── revokeAccessTokenForPartner ───────────────────────────────────────────

    public function test_revokeAccessTokenForPartner_only_revokes_matching_partner(): void
    {
        $partnerA = $this->insertPartner();
        $partnerB = $this->insertPartner();
        $rowA = (array) DB::table('api_partners')->where('id', $partnerA)->first();
        $rowB = (array) DB::table('api_partners')->where('id', $partnerB)->first();

        $issuedA = PartnerApiAuthService::issueAccessToken($rowA);
        $issuedB = PartnerApiAuthService::issueAccessToken($rowB);

        // Revoke only partner A's token using the partner-scoped method.
        $result = PartnerApiAuthService::revokeAccessTokenForPartner($issuedA['access_token'], $partnerA);
        $this->assertTrue($result, 'Should report 1 row revoked');

        // Partner A's token is now gone.
        $this->assertNull(PartnerApiAuthService::resolveAccessToken($issuedA['access_token']));
        // Partner B's token is still valid.
        $this->assertNotNull(PartnerApiAuthService::resolveAccessToken($issuedB['access_token']));
    }

    public function test_revokeAccessTokenForPartner_returns_false_for_wrong_partner(): void
    {
        $partnerA = $this->insertPartner();
        $partnerB = $this->insertPartner();
        $rowA = (array) DB::table('api_partners')->where('id', $partnerA)->first();

        $issued = PartnerApiAuthService::issueAccessToken($rowA);

        // Try to revoke partner A's token while claiming it belongs to partner B.
        $result = PartnerApiAuthService::revokeAccessTokenForPartner($issued['access_token'], $partnerB);

        $this->assertFalse($result, 'Should not revoke token belonging to a different partner');
        $this->assertNotNull(PartnerApiAuthService::resolveAccessToken($issued['access_token']));
    }
}
