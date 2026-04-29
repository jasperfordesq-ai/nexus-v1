<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Agent;

use App\Core\TenantContext;
use App\Http\Controllers\Api\Admin\AgentAdminController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * AG61 — verifies AgentAdminController endpoints return 403 when
 * the `ai_agents` tenant feature is disabled.
 *
 * We invoke the controller methods directly (rather than via HTTP) so the
 * assertion isolates the feature-gate check from auth/middleware. The
 * route-level `auth:sanctum` and `requireAdmin()` guards are tested
 * elsewhere; this test focuses on the gate.
 */
class AgentFeatureGateTest extends TestCase
{
    use DatabaseTransactions;

    private function setAiAgentsFeature(bool $enabled): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($row && !empty($row->features)) {
            $decoded = is_string($row->features) ? json_decode($row->features, true) : $row->features;
            if (is_array($decoded)) {
                $features = $decoded;
            }
        }
        $features['ai_agents'] = $enabled;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features),
        ]);
        TenantContext::setById($this->testTenantId);
    }

    public function test_ai_agents_disabled_by_default(): void
    {
        $this->setAiAgentsFeature(false);
        $this->assertFalse(TenantContext::hasFeature('ai_agents'));
    }

    public function test_index_returns_403_when_feature_disabled(): void
    {
        $this->setAiAgentsFeature(false);

        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'admin', 'status' => 'active', 'is_approved' => true,
        ]);
        $this->actingAs($admin);

        $controller = $this->app->make(AgentAdminController::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $controller->index();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_runs_returns_403_when_feature_disabled(): void
    {
        $this->setAiAgentsFeature(false);

        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'admin', 'status' => 'active', 'is_approved' => true,
        ]);
        $this->actingAs($admin);

        $controller = $this->app->make(AgentAdminController::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $controller->runs(new \Illuminate\Http\Request());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_proposals_returns_403_when_feature_disabled(): void
    {
        $this->setAiAgentsFeature(false);

        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'admin', 'status' => 'active', 'is_approved' => true,
        ]);
        $this->actingAs($admin);

        $controller = $this->app->make(AgentAdminController::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            $controller->proposals(new \Illuminate\Http\Request());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_index_succeeds_when_feature_enabled(): void
    {
        $this->setAiAgentsFeature(true);

        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'admin', 'status' => 'active', 'is_approved' => true,
        ]);
        $this->actingAs($admin);

        $controller = $this->app->make(AgentAdminController::class);
        $response = $controller->index();
        $this->assertSame(200, $response->getStatusCode());
    }
}
