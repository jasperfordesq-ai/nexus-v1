<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\TenantProvisioning;

use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class SlugReservedTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['provisioning.public_form_enabled' => true]);
    }

    public function test_reserved_slug_is_rejected_with_validation_error(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $response = $this->postJson('/api/v2/provisioning-requests', [
            'applicant_name'  => 'Maria',
            'applicant_email' => 'maria+reserved@example.org',
            'org_name'        => 'Reserved Org',
            'country_code'    => 'CH',
            'requested_slug'  => 'admin', // RESERVED
            'tenant_category' => 'community',
            'captcha_token'   => '7+4=11',
        ]);

        $response->assertStatus(422);
    }

    public function test_check_slug_endpoint_reports_reserved_slug(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $response = $this->getJson('/api/v2/provisioning-requests/check-slug/admin');
        $response->assertStatus(200);
        $response->assertJsonPath('data.available', false);
        $response->assertJsonPath('data.reason', 'reserved');
    }

    public function test_check_slug_reports_available_for_unused_slug(): void
    {
        if (! TenantProvisioningService::isAvailable()) {
            $this->markTestSkipped('tenant_provisioning_requests table not migrated');
        }

        $slug = 'fresh-' . substr(md5(uniqid('', true)), 0, 10);
        $response = $this->getJson('/api/v2/provisioning-requests/check-slug/' . $slug);
        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
    }
}
