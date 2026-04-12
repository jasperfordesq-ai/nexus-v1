<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationV2Controller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Verifies that FederationV2Controller::sendMessage and sendTransaction
 * honour the tenant-level `federation` feature flag.
 *
 * Both endpoints must return HTTP 403 with a clear error when the feature
 * has been disabled for the tenant. We call the controller methods directly
 * (rather than via the HTTP kernel) so the assertion isolates the gate
 * logic from unrelated middleware (auth, maintenance mode, etc.).
 */
class FederationFeatureGateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'general.maintenance_mode')
            ->delete();
    }

    private function setFederationFeature(int $tenantId, bool $enabled): void
    {
        $row = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$row) {
            $this->markTestSkipped("Tenant {$tenantId} does not exist.");
        }
        $features = [];
        if (!empty($row->features)) {
            $decoded = is_string($row->features) ? json_decode($row->features, true) : $row->features;
            if (is_array($decoded)) {
                $features = $decoded;
            }
        }
        $features['federation'] = $enabled;
        DB::table('tenants')->where('id', $tenantId)->update(['features' => json_encode($features)]);
        TenantContext::setById($tenantId);
    }

    public function test_has_feature_returns_false_after_disabling(): void
    {
        $this->setFederationFeature($this->testTenantId, false);
        $this->assertFalse(TenantContext::hasFeature('federation'));
    }

    public function test_send_message_returns_403_when_federation_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $this->actingAs($user);

        // Bind a request so request()->all() returns no input (empty payload path)
        $this->app->instance('request', Request::create(
            '/api/v2/federation/messages',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['receiver_id' => 999, 'receiver_tenant_id' => 3, 'body' => 'hi'])
        ));

        $controller = $this->app->make(FederationV2Controller::class);
        $response = $controller->sendMessage();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString(
            'Federation feature disabled',
            (string) $response->getContent()
        );
    }

    public function test_send_transaction_returns_403_when_federation_feature_disabled(): void
    {
        $this->setFederationFeature($this->testTenantId, false);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        $this->actingAs($user);

        $this->app->instance('request', Request::create(
            '/api/v2/federation/transactions',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['receiver_id' => 999, 'receiver_tenant_id' => 3, 'amount' => 2, 'description' => 'x'])
        ));

        $controller = $this->app->make(FederationV2Controller::class);
        $response = $controller->sendTransaction();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString(
            'Federation feature disabled',
            (string) $response->getContent()
        );
    }
}
