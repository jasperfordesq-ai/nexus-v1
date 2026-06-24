<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\FederationPeerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * FederationPeerServiceTest
 *
 * Covers:
 *   - isAvailable: returns true when table exists
 *   - listForTenant: returns redacted rows for the right tenant
 *   - findByPeerSlug: happy path and null when absent
 *   - findInboundBySourceSlug: delegates correctly
 *   - create: happy path (auto-generated secret, custom secret), validation guards
 *   - updateStatus: happy path, invalid status guard
 *   - rotateSecret: changes the secret, reveals it in result
 *   - delete: row removed, cross-tenant isolation
 *   - recordHandshake: updates last_handshake_at
 *   - listDiscoverable: returns only active peers, member-safe (no shared_secret)
 *   - findInboundContext: returns tenant+peer pair for a valid source slug
 *   - tenantId(): returns current TenantContext id
 */
class FederationPeerServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const OTHER_TENANT_ID = 999;

    private FederationPeerService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new FederationPeerService();
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * HTTPS URL using a well-known public IP directly (bypasses DNS in Docker).
     * 93.184.216.34 = example.com; passes OutboundUrlGuard::isPublicIp() check.
     */
    private const SAFE_BASE_URL = 'https://93.184.216.34';

    private function insertPeer(
        int $tenantId = self::TENANT_ID,
        string $slug = '',
        string $status = 'pending',
        string $secret = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    ): int {
        if ($slug === '') {
            $slug = 'peer-' . uniqid();
        }
        return (int) DB::table('caring_federation_peers')->insertGetId([
            'tenant_id'    => $tenantId,
            'peer_slug'    => $slug,
            'display_name' => 'Test Peer ' . $slug,
            'base_url'     => self::SAFE_BASE_URL,
            'shared_secret' => $secret,
            'status'       => $status,
            'notes'        => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // ── isAvailable ────────────────────────────────────────────────────────────

    public function test_isAvailable_returns_true_when_table_exists(): void
    {
        $this->assertTrue($this->svc->isAvailable());
    }

    // ── listForTenant ──────────────────────────────────────────────────────────

    public function test_listForTenant_returns_rows_for_correct_tenant_only(): void
    {
        $slug1 = 'list-peer-' . uniqid();
        $slug2 = 'list-peer-' . uniqid();
        $this->insertPeer(self::TENANT_ID, $slug1);
        $this->insertPeer(self::OTHER_TENANT_ID, $slug2); // other tenant — must not appear

        $rows = $this->svc->listForTenant(self::TENANT_ID);

        $slugs = array_column($rows, 'peer_slug');
        $this->assertContains($slug1, $slugs);
        $this->assertNotContains($slug2, $slugs);
    }

    public function test_listForTenant_redacts_shared_secret(): void
    {
        $this->insertPeer(self::TENANT_ID);

        $rows = $this->svc->listForTenant(self::TENANT_ID);

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('shared_secret', $row);
            $this->assertNull($row['shared_secret'], 'shared_secret must be null in list output');
            $this->assertTrue($row['shared_secret_set']);
        }
    }

    public function test_listForTenant_returns_empty_array_for_tenant_with_no_peers(): void
    {
        // Use a high tenant ID unlikely to have any rows
        $rows = $this->svc->listForTenant(99887766);
        $this->assertIsArray($rows);
        $this->assertSame([], $rows);
    }

    // ── findByPeerSlug ─────────────────────────────────────────────────────────

    public function test_findByPeerSlug_returns_row_with_secret_exposed(): void
    {
        $slug   = 'find-peer-' . uniqid();
        $secret = str_repeat('b', 64);
        $this->insertPeer(self::TENANT_ID, $slug, 'pending', $secret);

        $result = $this->svc->findByPeerSlug(self::TENANT_ID, $slug);

        $this->assertNotNull($result);
        $this->assertSame($slug, $result['peer_slug']);
        $this->assertSame($secret, $result['shared_secret']); // NOT redacted
    }

    public function test_findByPeerSlug_returns_null_when_slug_not_found(): void
    {
        $result = $this->svc->findByPeerSlug(self::TENANT_ID, 'no-such-peer-' . uniqid());
        $this->assertNull($result);
    }

    public function test_findByPeerSlug_is_tenant_isolated(): void
    {
        $slug = 'iso-peer-' . uniqid();
        $this->insertPeer(self::OTHER_TENANT_ID, $slug);

        // Looking for it under TENANT_ID should return null
        $result = $this->svc->findByPeerSlug(self::TENANT_ID, $slug);
        $this->assertNull($result);
    }

    // ── findInboundBySourceSlug ────────────────────────────────────────────────

    public function test_findInboundBySourceSlug_delegates_to_findByPeerSlug(): void
    {
        $slug = 'inbound-' . uniqid();
        $this->insertPeer(self::TENANT_ID, $slug, 'active');

        $result = $this->svc->findInboundBySourceSlug(self::TENANT_ID, $slug);

        $this->assertNotNull($result);
        $this->assertSame($slug, $result['peer_slug']);
    }

    // ── create ─────────────────────────────────────────────────────────────────

    public function test_create_inserts_row_and_returns_it_with_secret_exposed(): void
    {
        $slug = 'cr-peer-' . uniqid();

        $result = $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => $slug,
            'display_name' => 'Created Peer',
            'base_url'     => self::SAFE_BASE_URL,
        ]);

        $this->assertIsArray($result);
        $this->assertSame($slug, $result['peer_slug']);
        $this->assertSame(self::SAFE_BASE_URL, $result['base_url']);
        $this->assertSame('pending', $result['status']);
        $this->assertNotNull($result['shared_secret']);
        $this->assertSame(64, strlen($result['shared_secret'])); // 32 random bytes = 64 hex chars
        $this->assertTrue($result['shared_secret_set']);

        // Verify persisted
        $row = DB::table('caring_federation_peers')->where('peer_slug', $slug)->first();
        $this->assertNotNull($row);
    }

    public function test_create_accepts_caller_supplied_secret_of_sufficient_length(): void
    {
        $slug   = 'cs-peer-' . uniqid();
        $secret = str_repeat('x', 40);

        $result = $this->svc->create(self::TENANT_ID, [
            'peer_slug'     => $slug,
            'display_name'  => 'Custom Secret Peer',
            'base_url'      => self::SAFE_BASE_URL,
            'shared_secret' => $secret,
        ]);

        $this->assertSame($secret, $result['shared_secret']);
    }

    public function test_create_strips_trailing_slash_from_base_url(): void
    {
        $slug = 'trail-' . uniqid();

        $result = $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => $slug,
            'display_name' => 'Trailing Slash',
            'base_url'     => self::SAFE_BASE_URL . '/',
        ]);

        $this->assertSame(self::SAFE_BASE_URL, $result['base_url']);
    }

    public function test_create_throws_for_missing_peer_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('peer_slug, display_name, and base_url are required.');

        $this->svc->create(self::TENANT_ID, [
            'display_name' => 'No Slug',
            'base_url'     => 'https://example.test',
        ]);
    }

    public function test_create_throws_for_invalid_peer_slug_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('peer_slug must be lowercase alphanumeric with hyphens.');

        $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => 'UPPER_CASE', // uppercase not allowed
            'display_name' => 'Bad Slug',
            'base_url'     => 'https://example.test',
        ]);
    }

    public function test_create_throws_for_non_https_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url must be a valid HTTPS URL.');

        // Same public IP as SAFE_BASE_URL but http:// triggers the requireHttps guard.
        $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => 'http-peer-' . uniqid(),
            'display_name' => 'HTTP Peer',
            'base_url'     => 'http://93.184.216.34',
        ]);
    }

    public function test_create_throws_for_duplicate_peer_slug(): void
    {
        $slug = 'dup-peer-' . uniqid();
        $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => $slug,
            'display_name' => 'First',
            'base_url'     => self::SAFE_BASE_URL,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A peer with that slug is already registered.');

        $this->svc->create(self::TENANT_ID, [
            'peer_slug'    => $slug,
            'display_name' => 'Duplicate',
            'base_url'     => self::SAFE_BASE_URL,
        ]);
    }

    public function test_create_throws_for_short_supplied_secret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('shared_secret must be at least 32 characters when provided.');

        $this->svc->create(self::TENANT_ID, [
            'peer_slug'     => 'short-secret-' . substr(md5(uniqid()), 0, 8),
            'display_name'  => 'Short Secret',
            'base_url'      => self::SAFE_BASE_URL,
            'shared_secret' => 'tooshort',
        ]);
    }

    // ── updateStatus ──────────────────────────────────────────────────────────

    public function test_updateStatus_changes_status_and_returns_row(): void
    {
        $id = $this->insertPeer(self::TENANT_ID, '', 'pending');

        $result = $this->svc->updateStatus(self::TENANT_ID, $id, 'active');

        $this->assertSame('active', $result['status']);
        $this->assertNull($result['shared_secret']); // redacted

        $dbStatus = (string) DB::table('caring_federation_peers')->where('id', $id)->value('status');
        $this->assertSame('active', $dbStatus);
    }

    public function test_updateStatus_throws_for_invalid_status(): void
    {
        $id = $this->insertPeer(self::TENANT_ID);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status.');

        $this->svc->updateStatus(self::TENANT_ID, $id, 'unknown-status');
    }

    // ── rotateSecret ──────────────────────────────────────────────────────────

    public function test_rotateSecret_replaces_secret_and_reveals_it(): void
    {
        $oldSecret = str_repeat('a', 64);
        $id = $this->insertPeer(self::TENANT_ID, '', 'active', $oldSecret);

        $result = $this->svc->rotateSecret(self::TENANT_ID, $id);

        $this->assertNotNull($result['shared_secret']);
        $this->assertNotSame($oldSecret, $result['shared_secret']);
        $this->assertSame(64, strlen($result['shared_secret']));
        $this->assertTrue($result['shared_secret_set']);

        // Persisted value also changed
        $dbSecret = (string) DB::table('caring_federation_peers')->where('id', $id)->value('shared_secret');
        $this->assertNotSame($oldSecret, $dbSecret);
    }

    // ── delete ─────────────────────────────────────────────────────────────────

    public function test_delete_removes_the_row(): void
    {
        $id = $this->insertPeer(self::TENANT_ID);

        $this->svc->delete(self::TENANT_ID, $id);

        $exists = DB::table('caring_federation_peers')->where('id', $id)->exists();
        $this->assertFalse($exists);
    }

    public function test_delete_does_not_remove_row_belonging_to_different_tenant(): void
    {
        $id = $this->insertPeer(self::OTHER_TENANT_ID);

        // Attempting delete under TENANT_ID should be a no-op (wrong tenant)
        $this->svc->delete(self::TENANT_ID, $id);

        // Row for OTHER_TENANT_ID must still exist
        $exists = DB::table('caring_federation_peers')->where('id', $id)->exists();
        $this->assertTrue($exists);
    }

    // ── recordHandshake ────────────────────────────────────────────────────────

    public function test_recordHandshake_updates_last_handshake_at(): void
    {
        $id = $this->insertPeer(self::TENANT_ID);

        // Confirm it starts null
        $before = DB::table('caring_federation_peers')->where('id', $id)->value('last_handshake_at');
        $this->assertNull($before);

        $this->svc->recordHandshake(self::TENANT_ID, $id);

        $after = DB::table('caring_federation_peers')->where('id', $id)->value('last_handshake_at');
        $this->assertNotNull($after);
    }

    // ── listDiscoverable ───────────────────────────────────────────────────────

    public function test_listDiscoverable_returns_only_active_peers(): void
    {
        $activeSlug    = 'disc-active-' . uniqid();
        $pendingSlug   = 'disc-pending-' . uniqid();
        $suspendedSlug = 'disc-suspended-' . uniqid();

        $this->insertPeer(self::TENANT_ID, $activeSlug, 'active');
        $this->insertPeer(self::TENANT_ID, $pendingSlug, 'pending');
        $this->insertPeer(self::TENANT_ID, $suspendedSlug, 'suspended');

        $rows = $this->svc->listDiscoverable(self::TENANT_ID);

        $slugs = array_column($rows, 'slug');
        $this->assertContains($activeSlug, $slugs);
        $this->assertNotContains($pendingSlug, $slugs);
        $this->assertNotContains($suspendedSlug, $slugs);
    }

    public function test_listDiscoverable_does_not_include_shared_secret(): void
    {
        $this->insertPeer(self::TENANT_ID, 'disc-nosec-' . uniqid(), 'active');

        $rows = $this->svc->listDiscoverable(self::TENANT_ID);
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('shared_secret', $row);
            $this->assertArrayNotHasKey('notes', $row);
        }
    }

    // ── findInboundContext ─────────────────────────────────────────────────────

    public function test_findInboundContext_returns_tenant_and_peer_for_valid_source_slug(): void
    {
        $sourceSlug = 'inbound-ctx-' . uniqid();
        $this->insertPeer(self::TENANT_ID, $sourceSlug, 'active');

        // TENANT_ID 2 maps to slug 'hour-timebank' (set by TestCase::setUpTenantContext)
        $result = $this->svc->findInboundContext('hour-timebank', $sourceSlug);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('peer', $result);
        $this->assertSame(self::TENANT_ID, $result['tenant']['id']);
        $this->assertSame('hour-timebank', $result['tenant']['slug']);
        $this->assertSame($sourceSlug, $result['peer']['peer_slug']);
    }

    public function test_findInboundContext_returns_null_for_unknown_destination_slug(): void
    {
        $result = $this->svc->findInboundContext('no-such-tenant-' . uniqid(), 'any-source');
        $this->assertNull($result);
    }

    public function test_findInboundContext_returns_null_when_source_slug_not_registered(): void
    {
        $result = $this->svc->findInboundContext('hour-timebank', 'unregistered-' . uniqid());
        $this->assertNull($result);
    }

    // ── tenantId ───────────────────────────────────────────────────────────────

    public function test_tenantId_returns_current_tenant_context_id(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $this->assertSame(self::TENANT_ID, $this->svc->tenantId());
    }
}
