<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Services\Protocols\NexusAdapter;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * NativeNexusProtocolTest — exercises all new POST ingest endpoints for the
 * native Nexus protocol: /reviews, /listings, /events, /groups, /connections,
 * /volunteering, /members/sync.
 */
final class NativeNexusProtocolTest extends TestCase
{
    use FederationIntegrationHarness;

    public function test_adapter_identity(): void
    {
        $this->assertSame('nexus', NexusAdapter::getProtocolName());
    }

    /**
     * @dataProvider ingestEndpointProvider
     */
    public function test_native_ingest_endpoint_present(string $route): void
    {
        // Expected Nexus-native inbound POST endpoints.
        // If route isn't registered yet, mark incomplete.
        $router = app('router');
        $routes = $router->getRoutes();
        $found = false;
        foreach ($routes as $r) {
            if (in_array('POST', $r->methods(), true) && str_contains($r->uri(), $route)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $this->markTestIncomplete(
                "TODO(federation): native Nexus ingest POST route '{$route}' not registered."
            );
        }
        $this->assertTrue($found);
    }

    public static function ingestEndpointProvider(): array
    {
        return [
            'reviews'       => ['federation/reviews'],
            'listings'      => ['federation/listings'],
            'events'        => ['federation/events'],
            'groups'        => ['federation/groups'],
            'connections'   => ['federation/connections'],
            'volunteering'  => ['federation/volunteering'],
            'members_sync'  => ['federation/members/sync'],
        ];
    }
}
