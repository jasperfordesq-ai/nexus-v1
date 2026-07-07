<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminFederationSensitiveAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_standard_admin_cannot_access_super_only_federation_setup_endpoints(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $endpoints = [
            ['GET', '/v2/admin/federation/api-keys', []],
            ['POST', '/v2/admin/federation/api-keys', ['name' => 'Blocked key']],
            ['GET', '/v2/admin/federation/data', []],
            ['GET', '/v2/admin/federation/export/users', []],
            ['POST', '/v2/admin/federation/data/export', []],
            ['POST', '/v2/admin/federation/data/import', []],
            ['POST', '/v2/admin/federation/data/purge', ['days' => 365]],
            ['GET', '/v2/admin/federation/aggregate-consent', []],
            ['PUT', '/v2/admin/federation/aggregate-consent', ['enabled' => true]],
            ['POST', '/v2/admin/federation/aggregate-consent/rotate-secret', []],
            ['GET', '/v2/admin/federation/aggregate-consent/audit-log', []],
            ['GET', '/v2/admin/federation/aggregate-consent/preview', []],
            ['GET', '/v2/admin/federation/webhooks', []],
            ['POST', '/v2/admin/federation/webhooks', ['url' => 'https://example.test/webhook', 'events' => ['message.sent']]],
            ['POST', '/v2/admin/federation/webhook-logs/1/retry', []],
            ['GET', '/v2/admin/federation/external-partners', []],
            ['POST', '/v2/admin/federation/external-partners', ['name' => 'Blocked partner']],
            ['GET', '/v2/admin/federation/cc-config', []],
            ['PUT', '/v2/admin/federation/cc-config', ['node_slug' => 'test-node']],
            ['PUT', '/v2/admin/federation/settings', ['federation_enabled' => true]],
        ];

        foreach ($endpoints as [$method, $uri, $payload]) {
            $response = match ($method) {
                'GET' => $this->apiGet($uri),
                'POST' => $this->apiPost($uri, $payload ?? []),
                'PUT' => $this->apiPut($uri, $payload ?? []),
                default => throw new \InvalidArgumentException('Unsupported method'),
            };

            $this->assertSame(403, $response->getStatusCode(), "{$method} {$uri} should require super-admin access");
        }
    }
}
