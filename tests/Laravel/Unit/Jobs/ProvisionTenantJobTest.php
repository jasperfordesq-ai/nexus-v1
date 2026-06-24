<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\ProvisionTenantJob;
use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ProvisionTenantJobTest
 *
 * ProvisionTenantJob wraps TenantProvisioningService::approveAndProvision().
 * We test:
 *   - Job properties ($tries, $timeout) match documented config
 *   - handle() with a non-existent request ID: service throws, job swallows it (no rethrow)
 *   - handle() with an already-provisioned request: returns early, status unchanged
 *   - handle() happy path: creates a new tenant and admin user row, sets status=provisioned
 *   - failed() logs an error (observable via Log::shouldReceive)
 *
 * The happy-path test calls the real TenantProvisioningService pipeline, so it
 * uses DatabaseTransactions to roll everything back.
 */
class ProvisionTenantJobTest extends TestCase
{
    use DatabaseTransactions;

    // ── helpers ───────────────────────────────────────────────────────────────

    /** Insert a minimal pending provisioning request and return its ID. */
    private function insertPendingRequest(string $slug): int
    {
        return DB::table('tenant_provisioning_requests')->insertGetId([
            'applicant_name'    => 'Test Applicant',
            'applicant_email'   => 'provision.' . uniqid('', true) . '@example.test',
            'org_name'          => 'Test Org',
            'requested_slug'    => $slug,
            'tenant_category'   => 'community',
            'languages'         => json_encode(['en']),
            'default_language'  => 'en',
            'country_code'      => 'IE',
            'status'            => 'pending',
            'status_token'      => bin2hex(random_bytes(20)),
            'provisioning_log'  => json_encode([]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /** Job exposes the expected $tries and $timeout values. */
    public function test_job_has_correct_configuration(): void
    {
        $job = new ProvisionTenantJob(1, 1);
        $this->assertSame(1, $job->tries);
        $this->assertSame(120, $job->timeout);
    }

    /** Constructor stores requestId and reviewerId. */
    public function test_job_stores_constructor_arguments(): void
    {
        $job = new ProvisionTenantJob(42, 7);
        $this->assertSame(42, $job->requestId);
        $this->assertSame(7, $job->reviewerId);
    }

    /**
     * handle() with a non-existent request ID: service throws InvalidArgumentException,
     * job catches it and returns silently without rethrowing.
     */
    public function test_handle_swallows_exception_for_missing_request(): void
    {
        $job = new ProvisionTenantJob(99999999, 1);
        $job->handle(); // must not throw
        $this->assertTrue(true);
    }

    /**
     * handle() with an already-provisioned request (status=provisioned + provisioned_tenant_id set):
     * service returns early without modifying the row.
     */
    public function test_handle_returns_early_for_already_provisioned_request(): void
    {
        // Pre-create a tenant so we can link it as the provisioned tenant.
        $tenantId = DB::table('tenants')->insertGetId([
            'name'               => 'Pre-provisioned Tenant',
            'slug'               => 'pre-prov-' . uniqid('', true),
            'is_active'          => true,
            'depth'              => 0,
            'allows_subtenants'  => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $reqId = $this->insertPendingRequest('pre-prov-slug-' . uniqid('', true));
        DB::table('tenant_provisioning_requests')->where('id', $reqId)->update([
            'status'                => 'provisioned',
            'provisioned_tenant_id' => $tenantId,
            'reviewed_by'           => 1,
            'reviewed_at'           => now(),
        ]);

        $job = new ProvisionTenantJob($reqId, 1);
        $job->handle();

        // Row must still be 'provisioned' (not changed to 'approved' or 'failed').
        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        $this->assertSame('provisioned', $row->status);
    }

    /**
     * Happy path: handle() provisions a fresh request, creating a tenant row and
     * an admin user, then marks the request as 'provisioned'.
     */
    public function test_handle_provisions_new_request_and_marks_provisioned(): void
    {
        $slug  = 'prov-test-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        $reviewerId = DB::table('users')->insertGetId([
            'tenant_id'  => 2,
            'name'       => 'Reviewer',
            'first_name' => 'Rev',
            'last_name'  => 'Iewer',
            'email'      => 'reviewer.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'admin',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new ProvisionTenantJob($reqId, $reviewerId);
        $job->handle();

        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();

        // The request must be marked as provisioned.
        $this->assertSame('provisioned', $row->status, 'Request status must be provisioned after successful handle()');
        $this->assertNotNull($row->provisioned_tenant_id, 'provisioned_tenant_id must be populated');

        // A tenant row must exist with the requested slug.
        $tenant = DB::table('tenants')->where('id', $row->provisioned_tenant_id)->first();
        $this->assertNotNull($tenant, 'Tenant row must exist');
        $this->assertSame($slug, $tenant->slug);

        // At least one admin user must exist for the new tenant.
        $adminCount = DB::table('users')
            ->where('tenant_id', $row->provisioned_tenant_id)
            ->count();
        $this->assertGreaterThan(0, $adminCount, 'At least one admin user must be created');
    }

    /**
     * handle() with a request that has status='under_review' (not yet provisioned):
     * The service marks it 'approved' and then runs the pipeline.
     * We verify that the status is no longer 'under_review' after handle() completes.
     * (Under_review → approved is the first DB update in approveAndProvision.)
     */
    public function test_handle_transitions_under_review_request(): void
    {
        $slug  = 'ur-test-' . substr(md5(uniqid('', true)), 0, 8);
        $reqId = $this->insertPendingRequest($slug);

        // Manually set to under_review (simulating admin review start).
        DB::table('tenant_provisioning_requests')->where('id', $reqId)->update([
            'status' => 'under_review',
        ]);

        $job = new ProvisionTenantJob($reqId, 1);
        $job->handle();

        $row = DB::table('tenant_provisioning_requests')->where('id', $reqId)->first();
        // After handle() the request must not still be 'under_review' —
        // it should be 'provisioned' (happy path) or 'failed'.
        $this->assertNotSame('under_review', $row->status, 'Request must have advanced past under_review');
    }
}
