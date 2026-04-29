<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\TenantProvisioning;

use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class PublicSubmitTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['provisioning.public_form_enabled' => true]);
    }

    public function test_public_submit_creates_pending_request_with_hashed_ip(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $email = 'pilot+' . uniqid('', true) . '@example.org';
        $slug  = 'test-' . substr(md5(uniqid('', true)), 0, 10);

        $response = $this->postJson('/api/v2/provisioning-requests', [
            'applicant_name'   => 'Maria Tester',
            'applicant_email'  => $email,
            'org_name'         => 'Test Cooperative',
            'country_code'     => 'CH',
            'requested_slug'   => $slug,
            'tenant_category'  => 'kiss_cooperative',
            'languages'        => ['de', 'en'],
            'default_language' => 'de',
            'intended_use'     => 'Test',
            'captcha_token'    => '7+4=11',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'status', 'status_token']]);

        $row = DB::table(TenantProvisioningService::TABLE)
            ->where('applicant_email', $email)
            ->first();

        $this->assertNotNull($row, 'Provisioning request should have been inserted');
        $this->assertSame('pending', $row->status);
        $this->assertNotEmpty($row->ip_hash, 'IP hash should be populated');
        // ip_hash must be hashed (sha256 = 64 hex chars), never raw IP
        $this->assertSame(64, strlen((string) $row->ip_hash));
        $this->assertSame(1, preg_match('/^[a-f0-9]+$/', (string) $row->ip_hash));
        $this->assertNotNull($row->status_token);
    }

    public function test_returns_503_when_form_disabled(): void
    {
        config(['provisioning.public_form_enabled' => false]);

        $response = $this->postJson('/api/v2/provisioning-requests', [
            'applicant_name'  => 'Maria',
            'applicant_email' => 'maria@example.org',
            'org_name'        => 'Coop',
            'requested_slug'  => 'whatever-' . uniqid(),
            'tenant_category' => 'community',
        ]);

        $response->assertStatus(503);
    }
}
