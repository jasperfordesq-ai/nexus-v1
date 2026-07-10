<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class PwaManifestControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_path_tenant_gets_unique_identity_scope_and_shortcuts(): void
    {
        $response = $this->apiGet('/v2/pwa/manifest?path=/hour-timebank/listings');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');
        $response->assertJsonPath('name', 'Hour Timebank');
        $response->assertJsonPath('id', '/hour-timebank/');
        $response->assertJsonPath('start_url', '/hour-timebank/');
        $response->assertJsonPath('scope', '/hour-timebank/');
        $response->assertJsonPath('shortcuts.0.url', '/hour-timebank/listings');
        $response->assertJsonPath('shortcuts.1.url', '/hour-timebank/messages');
        $response->assertJsonPath('shortcuts.2.url', '/hour-timebank/wallet');
    }

    public function test_shared_host_manifest_resolves_path_tenant_from_master_context(): void
    {
        TenantContext::reset();
        TenantContext::setById(1);

        $response = $this->apiGet('/v2/pwa/manifest?path=/hour-timebank/login');

        $response->assertOk();
        $response->assertJsonPath('name', 'Hour Timebank');
        $response->assertJsonPath('id', '/hour-timebank/');
        $response->assertJsonPath('start_url', '/hour-timebank/');
        $response->assertJsonPath('scope', '/hour-timebank/');
        $response->assertJsonPath('shortcuts.0.url', '/hour-timebank/listings');
    }

    public function test_custom_domain_tenant_keeps_root_scope(): void
    {
        DB::table('tenants')->where('id', 1)->update([
            'slug' => 'listings',
            'is_active' => true,
        ]);
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'domain' => 'tenant.example.test',
        ]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $response = $this->apiGet('/v2/pwa/manifest?path=/listings');

        $response->assertOk();
        $response->assertJsonPath('id', '/');
        $response->assertJsonPath('name', 'Hour Timebank');
        $response->assertJsonPath('start_url', '/');
        $response->assertJsonPath('scope', '/');
        $response->assertJsonPath('shortcuts.0.url', '/listings');
    }
}
