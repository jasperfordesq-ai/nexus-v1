<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminCaringCommunityControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }

        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', $this->testTenantId)
            ->update(['features' => json_encode($features)]);
        TenantContext::setById($this->testTenantId);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2?: array<string, mixed>}>
     */
    public static function disabledAdminRoutes(): array
    {
        return [
            'workflow summary' => ['GET', '/v2/admin/caring-community/workflow'],
            'workflow policy update' => ['PUT', '/v2/admin/caring-community/workflow/policy', [
                'review_sla_days' => 5,
            ]],
            'review assignment' => ['PUT', '/v2/admin/caring-community/workflow/reviews/999/assign', [
                'assigned_to' => null,
            ]],
            'review escalation' => ['PUT', '/v2/admin/caring-community/workflow/reviews/999/escalate', [
                'note' => 'Needs coordinator review.',
            ]],
            'role presets' => ['GET', '/v2/admin/caring-community/role-presets'],
            'role preset install' => ['POST', '/v2/admin/caring-community/role-presets/install', [
                'preset' => 'municipality_admin',
            ]],
        ];
    }

    /**
     * @dataProvider disabledAdminRoutes
     *
     * @param array<string, mixed> $payload
     */
    public function test_admin_caring_community_routes_return_403_when_feature_disabled(
        string $method,
        string $uri,
        array $payload = [],
    ): void {
        $this->setCaringCommunityFeature(false);
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = match ($method) {
            'GET' => $this->apiGet($uri),
            'POST' => $this->apiPost($uri, $payload),
            'PUT' => $this->apiPut($uri, $payload),
            default => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };

        $response->assertStatus(403);
        $response->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    public function test_workflow_route_returns_401_for_unauthenticated_user(): void
    {
        $this->setCaringCommunityFeature(true);

        $response = $this->apiGet('/v2/admin/caring-community/workflow');

        $response->assertStatus(401);
    }
}
