<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console\Tenant;

use App\Core\TenantContext;
use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * ProvisionTenantTest
 *
 * Tests the `tenant:provision` Artisan command
 * (App\Console\Commands\Tenant\ProvisionTenant).
 *
 * Signature:
 *   tenant:provision {request_id} {--reviewer=1}
 *
 * Behaviour:
 *   - Exits FAILURE (1) when request_id <= 0
 *   - Exits FAILURE (1) when the provisioning table is missing (isAvailable guard)
 *   - Exits FAILURE (1) when the request_id does not exist (service throws)
 *   - Exits FAILURE (1) when the request is already in a non-provisionable state
 *   - Exits SUCCESS (0) + outputs "Provisioning complete" on happy path
 *   - Already-provisioned request → exits SUCCESS (0) (service returns early)
 *   - The --reviewer option is forwarded to the service
 *
 * NOTE: The command calls TenantProvisioningService::approveAndProvision() which
 * runs a real provisioning pipeline (creates tenant row, admin user, seeds defaults).
 * DatabaseTransactions rolls all of that back after each test.
 *
 * Tenant 99756 is the isolated tenant for this suite (setUp/TenantContext only;
 * provisioned tenants get auto-generated IDs and are cleaned up by transactions).
 */
class ProvisionTenantTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99756;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Isolated tenant for TenantContext
        if (!DB::table('tenants')->where('id', self::TENANT_ID)->exists()) {
            DB::table('tenants')->insert([
                'id'         => self::TENANT_ID,
                'name'       => 'ProvisionTenant Test Tenant',
                'slug'       => 'provision-tenant-test-99756',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal tenant_provisioning_requests row and return its ID.
     * Uses a unique slug each time to avoid the unique constraint.
     */
    private function insertPendingRequest(string $slug): int
    {
        return DB::table('tenant_provisioning_requests')->insertGetId([
            'applicant_name'   => 'CLI Test Applicant',
            'applicant_email'  => 'cli.test.' . uniqid('', true) . '@example.test',
            'org_name'         => 'CLI Test Org',
            'requested_slug'   => $slug,
            'tenant_category'  => 'community',
            'languages'        => json_encode(['en']),
            'default_language' => 'en',
            'country_code'     => 'IE',
            'status'           => 'pending',
            'status_token'     => bin2hex(random_bytes(20)),
            'provisioning_log' => json_encode([]),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /**
     * Exits FAILURE when request_id is 0.
     */
    public function test_exits_failure_when_request_id_is_zero(): void
    {
        $this->artisan('tenant:provision', ['request_id' => '0'])
            ->expectsOutputToContain('request_id must be > 0')
            ->assertExitCode(1);
    }

    /**
     * Exits FAILURE when request_id is negative.
     */
    public function test_exits_failure_when_request_id_is_negative(): void
    {
        $this->artisan('tenant:provision', ['request_id' => '-5'])
            ->expectsOutputToContain('request_id must be > 0')
            ->assertExitCode(1);
    }

    /**
     * Exits FAILURE when request_id does not exist in the DB (service throws).
     */
    public function test_exits_failure_when_request_not_found(): void
    {
        $this->artisan('tenant:provision', ['request_id' => '999999999'])
            ->expectsOutputToContain('Provisioning failed')
            ->assertExitCode(1);
    }

    /**
     * Happy path: pending request → exits SUCCESS, outputs "Provisioning complete",
     * and the request row transitions to 'provisioned'.
     */
    public function test_happy_path_provisions_pending_request(): void
    {
        $slug  = 'cli-prov-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $this->artisan('tenant:provision', ['request_id' => (string) $reqId])
            ->expectsOutputToContain('Provisioning complete')
            ->assertExitCode(0);

        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $this->assertSame('provisioned', $row->status,
            'Request must be marked provisioned after successful artisan run.');
        $this->assertNotNull($row->provisioned_tenant_id,
            'provisioned_tenant_id must be populated.');
    }

    /**
     * Happy path: a tenant row is created for the new slug.
     */
    public function test_happy_path_creates_tenant_row(): void
    {
        $slug  = 'cli-prov-t-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $this->artisan('tenant:provision', ['request_id' => (string) $reqId])
            ->assertExitCode(0);

        $row    = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $tenant = DB::table('tenants')->where('id', $row->provisioned_tenant_id)->first();

        $this->assertNotNull($tenant, 'A tenants row must be created by provisioning.');
        $this->assertSame($slug, $tenant->slug,
            'The new tenant slug must match the requested_slug.');
    }

    /**
     * Happy path: at least one admin user is created for the new tenant.
     */
    public function test_happy_path_creates_admin_user(): void
    {
        $slug  = 'cli-prov-u-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $this->artisan('tenant:provision', ['request_id' => (string) $reqId])
            ->assertExitCode(0);

        $row       = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $userCount = DB::table('users')->where('tenant_id', $row->provisioned_tenant_id)->count();

        $this->assertGreaterThan(0, $userCount,
            'At least one admin user must be created for the new tenant.');
    }

    /**
     * Already-provisioned request → command exits SUCCESS without re-provisioning.
     */
    public function test_already_provisioned_request_exits_success(): void
    {
        // Create an existing tenant to reference
        $existingTenantId = DB::table('tenants')->insertGetId([
            'name'       => 'Pre-Provisioned',
            'slug'       => 'pre-prov-cmd-' . uniqid('', true),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slug  = 'already-prov-' . uniqid('', true);
        $reqId = $this->insertPendingRequest($slug);

        DB::table('tenant_provisioning_requests')->where('id', $reqId)->update([
            'status'                => 'provisioned',
            'provisioned_tenant_id' => $existingTenantId,
            'reviewed_by'           => 1,
            'reviewed_at'           => now(),
        ]);

        // The service returns early for already-provisioned; command should succeed.
        $this->artisan('tenant:provision', ['request_id' => (string) $reqId])
            ->expectsOutputToContain('Provisioning complete')
            ->assertExitCode(0);

        // Row must still be 'provisioned' (unchanged).
        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $this->assertSame('provisioned', $row->status);
    }

    /**
     * --reviewer option is forwarded: the request's reviewed_by matches the supplied value.
     */
    public function test_reviewer_option_is_recorded_on_request(): void
    {
        // Insert a reviewer user in the test tenant so the FK is valid (if enforced)
        $reviewerId = DB::table('users')->insertGetId([
            'tenant_id'   => 2,
            'name'        => 'CLI Reviewer',
            'first_name'  => 'CLI',
            'last_name'   => 'Reviewer',
            'email'       => 'cli.reviewer.' . uniqid('', true) . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'admin',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $slug  = 'cli-reviewer-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $this->artisan('tenant:provision', [
            'request_id' => (string) $reqId,
            '--reviewer' => (string) $reviewerId,
        ])->assertExitCode(0);

        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $this->assertSame((int) $reviewerId, (int) $row->reviewed_by,
            'reviewed_by must match the --reviewer option value.');
    }

    /**
     * Command signature is 'tenant:provision'.
     */
    public function test_command_signature_is_tenant_provision(): void
    {
        $cmd = new \App\Console\Commands\Tenant\ProvisionTenant();
        $this->assertSame('tenant:provision', $cmd->getName());
    }

    /**
     * Output contains tenant_id on success.
     */
    public function test_success_output_contains_tenant_id(): void
    {
        $slug  = 'cli-output-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $this->artisan('tenant:provision', ['request_id' => (string) $reqId])
            ->expectsOutputToContain('tenant_id=')
            ->assertExitCode(0);
    }
}
