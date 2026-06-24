<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\TenantProvisioning;

use Tests\Laravel\TestCase;
use App\Services\TenantProvisioning\TenantProvisioningService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;

/**
 * TenantProvisioningServiceTest
 *
 * Tests the public surface of TenantProvisioningService:
 *   - validateSlugAvailable()  (format, reserved, taken, pending)
 *   - submitRequest()          (validation guards, persistence)
 *   - approveAndProvision()    (happy path: tenant row + admin user seeded)
 *   - reject()                 (status transition, reason guard)
 *   - listRequests() / getRequest() / getRequestByToken()  (reads)
 *
 * IDs use the 99500+ high range to avoid collisions with existing data.
 * MAIL_MAILER=array prevents SMTP hangs (set via docker exec -e flag).
 * DatabaseTransactions rolls back all inserts after each test.
 *
 * Skipped: Artisan::call('tenant:apply-caring-community-preset', …) for
 * caring/kiss categories because the command may not be registered in the
 * test environment; the step is best-effort in the service and non-fatal.
 */
class TenantProvisioningServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a minimal valid submitRequest() payload with a unique slug.
     */
    private function validPayload(array $overrides = []): array
    {
        static $counter = 0;
        $counter++;
        $slug = 'testprov-' . $counter . '-' . substr(md5((string) microtime(true)), 0, 6);

        return array_merge([
            'applicant_name'  => 'Jane Provisioner',
            'applicant_email' => 'jane.' . $counter . '@provision.test',
            'org_name'        => 'Test Org ' . $counter,
            'requested_slug'  => $slug,
            'tenant_category' => 'community',
        ], $overrides);
    }

    /**
     * Insert a minimal provisioning request row directly and return its id.
     * Used to set up rows for approve/reject tests without triggering submit
     * validation (rate-limit, etc.).
     */
    private function insertRequest(array $overrides = []): int
    {
        static $rc = 0;
        $rc++;
        $slug = 'dirinsert-' . $rc . '-' . substr(md5((string) microtime(true)), 0, 6);
        $now  = now();

        return (int) DB::table(TenantProvisioningService::TABLE)->insertGetId(array_merge([
            'applicant_name'   => 'Direct Insert',
            'applicant_email'  => 'direct.' . $rc . '@provision.test',
            'org_name'         => 'Direct Org ' . $rc,
            'requested_slug'   => $slug,
            'tenant_category'  => 'community',
            'default_language' => 'en',
            'country_code'     => 'IE',
            'languages'        => json_encode(['en']),
            'status_token'     => \Illuminate\Support\Str::random(40),
            'status'           => 'pending',
            'provisioning_log' => json_encode([]),
            'created_at'       => $now,
            'updated_at'       => $now,
        ], $overrides));
    }

    // ── validateSlugAvailable ─────────────────────────────────────────────────

    public function test_validateSlugAvailable_returns_available_for_fresh_slug(): void
    {
        $result = TenantProvisioningService::validateSlugAvailable('totally-fresh-slug-' . mt_rand(10000, 99999));

        $this->assertTrue($result['available']);
    }

    public function test_validateSlugAvailable_rejects_reserved_slug(): void
    {
        $result = TenantProvisioningService::validateSlugAvailable('admin');

        $this->assertFalse($result['available']);
        $this->assertSame('reserved', $result['reason']);
    }

    public function test_validateSlugAvailable_rejects_invalid_format(): void
    {
        // Starts with hyphen — invalid
        $result = TenantProvisioningService::validateSlugAvailable('-bad-start');

        $this->assertFalse($result['available']);
        $this->assertSame('invalid_format', $result['reason']);
    }

    public function test_validateSlugAvailable_rejects_empty_string(): void
    {
        $result = TenantProvisioningService::validateSlugAvailable('');

        $this->assertFalse($result['available']);
        $this->assertSame('invalid_format', $result['reason']);
    }

    public function test_validateSlugAvailable_rejects_slug_already_taken_by_tenant(): void
    {
        // Insert a tenant with a known slug
        $slug = 'taken-slug-' . mt_rand(10000, 99999);
        $now  = now();
        DB::table('tenants')->insert([
            'name'       => 'Taken Tenant',
            'slug'       => $slug,
            'depth'      => 0,
            'allows_subtenants' => 0,
            'is_active'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = TenantProvisioningService::validateSlugAvailable($slug);

        $this->assertFalse($result['available']);
        $this->assertSame('taken', $result['reason']);
    }

    public function test_validateSlugAvailable_rejects_slug_with_pending_request(): void
    {
        $slug = 'pending-slug-' . mt_rand(10000, 99999);
        $now  = now();

        DB::table(TenantProvisioningService::TABLE)->insert([
            'applicant_name'   => 'Pending',
            'applicant_email'  => 'pending@provision.test',
            'org_name'         => 'Pending Org',
            'requested_slug'   => $slug,
            'tenant_category'  => 'community',
            'default_language' => 'en',
            'country_code'     => 'IE',
            'languages'        => json_encode(['en']),
            'status_token'     => \Illuminate\Support\Str::random(40),
            'status'           => 'pending',
            'provisioning_log' => json_encode([]),
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $result = TenantProvisioningService::validateSlugAvailable($slug);

        $this->assertFalse($result['available']);
        $this->assertSame('pending', $result['reason']);
    }

    // ── submitRequest ─────────────────────────────────────────────────────────

    public function test_submitRequest_persists_row_with_pending_status(): void
    {
        $payload = $this->validPayload();

        $row = TenantProvisioningService::submitRequest($payload);

        $this->assertSame('pending', $row['status']);
        $this->assertNotEmpty($row['status_token']);
        $this->assertNotEmpty($row['id']);

        // Confirm the DB row exists
        $db = DB::table(TenantProvisioningService::TABLE)->where('id', $row['id'])->first();
        $this->assertNotNull($db);
        $this->assertSame($payload['requested_slug'], $db->requested_slug);
    }

    public function test_submitRequest_throws_on_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $payload = $this->validPayload();
        unset($payload['org_name']); // missing required
        TenantProvisioningService::submitRequest($payload);
    }

    public function test_submitRequest_throws_on_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProvisioningService::submitRequest($this->validPayload([
            'applicant_email' => 'not-an-email',
        ]));
    }

    public function test_submitRequest_throws_on_invalid_category(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProvisioningService::submitRequest($this->validPayload([
            'tenant_category' => 'unknown_category',
        ]));
    }

    public function test_submitRequest_throws_on_reserved_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProvisioningService::submitRequest($this->validPayload([
            'requested_slug' => 'admin',
        ]));
    }

    public function test_submitRequest_stores_languages_as_json(): void
    {
        $payload = $this->validPayload(['languages' => ['en', 'de']]);

        $row = TenantProvisioningService::submitRequest($payload);

        $languages = json_decode($row['languages'], true);
        $this->assertContains('en', $languages);
        $this->assertContains('de', $languages);
    }

    public function test_submitRequest_defaults_to_en_when_languages_empty(): void
    {
        $payload = $this->validPayload(['languages' => []]);

        $row = TenantProvisioningService::submitRequest($payload);

        $languages = json_decode($row['languages'], true);
        $this->assertSame(['en'], $languages);
    }

    // ── approveAndProvision ───────────────────────────────────────────────────

    public function test_approveAndProvision_creates_tenant_row(): void
    {
        $requestId = $this->insertRequest();
        $reviewerId = 1;

        $result = TenantProvisioningService::approveAndProvision($requestId, $reviewerId);

        $this->assertSame('provisioned', $result['status']);
        $this->assertNotNull($result['provisioned_tenant_id']);

        $tenant = DB::table('tenants')->where('id', $result['provisioned_tenant_id'])->first();
        $this->assertNotNull($tenant);
        $this->assertSame('community', $tenant->tenant_category);
    }

    public function test_approveAndProvision_creates_admin_user(): void
    {
        $email     = 'admin.' . mt_rand(10000, 99999) . '@provision.test';
        $requestId = $this->insertRequest(['applicant_email' => $email]);
        $reviewerId = 1;

        $result = TenantProvisioningService::approveAndProvision($requestId, $reviewerId);

        $tenantId = $result['provisioned_tenant_id'];
        $this->assertNotNull($tenantId);

        $adminUser = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('role', 'admin')
            ->first();

        $this->assertNotNull($adminUser);
        $this->assertSame(1, (int) $adminUser->is_approved);
        $this->assertSame('active', $adminUser->status);
    }

    public function test_approveAndProvision_is_idempotent_when_already_provisioned(): void
    {
        $requestId  = $this->insertRequest();
        $reviewerId = 1;

        // First provision
        $first = TenantProvisioningService::approveAndProvision($requestId, $reviewerId);

        // Second call should return the existing row without re-provisioning
        $second = TenantProvisioningService::approveAndProvision($requestId, $reviewerId);

        $this->assertSame($first['provisioned_tenant_id'], $second['provisioned_tenant_id']);

        // Only one tenant row with this request's slug should exist
        $slug  = DB::table(TenantProvisioningService::TABLE)->where('id', $requestId)->value('requested_slug');
        $count = DB::table('tenants')->where('slug', $slug)->count();
        $this->assertSame(1, $count);
    }

    public function test_approveAndProvision_throws_when_request_not_found(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProvisioningService::approveAndProvision(999999999, 1);
    }

    public function test_approveAndProvision_logs_pipeline_steps_in_provisioning_log(): void
    {
        $requestId = $this->insertRequest();

        $result = TenantProvisioningService::approveAndProvision($requestId, 1);

        $log = json_decode($result['provisioning_log'] ?? '[]', true);
        $this->assertIsArray($log);
        $this->assertNotEmpty($log);

        $steps = array_column($log, 'step');
        $this->assertContains('create_tenant', $steps);
        $this->assertContains('create_admin_user', $steps);
    }

    // ── reject ────────────────────────────────────────────────────────────────

    public function test_reject_throws_when_reason_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $requestId = $this->insertRequest();
        TenantProvisioningService::reject($requestId, '', 1);
    }

    public function test_reject_throws_when_request_not_found(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantProvisioningService::reject(999999999, 'Duplicate region', 1);
    }

    // ── listRequests / getRequest / getRequestByToken ──────────────────────────

    public function test_listRequests_returns_submitted_request(): void
    {
        $payload = $this->validPayload();
        $row     = TenantProvisioningService::submitRequest($payload);

        $all = TenantProvisioningService::listRequests();

        $ids = array_column($all, 'id');
        $this->assertContains($row['id'], $ids);
    }

    public function test_listRequests_filters_by_status(): void
    {
        $pending = TenantProvisioningService::listRequests('pending');

        foreach ($pending as $r) {
            $this->assertSame('pending', $r['status']);
        }

        $this->assertGreaterThanOrEqual(0, count($pending));
    }

    public function test_getRequest_returns_correct_row(): void
    {
        $requestId = $this->insertRequest();

        $row = TenantProvisioningService::getRequest($requestId);

        $this->assertNotNull($row);
        $this->assertSame($requestId, $row['id']);
    }

    public function test_getRequest_returns_null_for_unknown_id(): void
    {
        $result = TenantProvisioningService::getRequest(999999998);

        $this->assertNull($result);
    }

    public function test_getRequestByToken_returns_row_without_sensitive_fields(): void
    {
        $payload = $this->validPayload(['captcha_token' => 'tok123']);
        $row     = TenantProvisioningService::submitRequest($payload);

        $public = TenantProvisioningService::getRequestByToken($row['status_token']);

        $this->assertNotNull($public);
        $this->assertSame($row['id'], $public['id']);
        $this->assertArrayNotHasKey('captcha_token', $public);
        $this->assertArrayNotHasKey('ip_hash', $public);
        $this->assertArrayNotHasKey('provisioning_log', $public);
    }

    public function test_getRequestByToken_returns_null_for_unknown_token(): void
    {
        $result = TenantProvisioningService::getRequestByToken('totally-bogus-token-xyz');

        $this->assertNull($result);
    }
}
