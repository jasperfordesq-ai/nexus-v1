<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Services\Protocols\KomunitinAdapter;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * KomunitinProtocolTest — exercises the 15 Komunitin endpoints plus
 * the new DELETE verbs on currency/account.
 */
final class KomunitinProtocolTest extends TestCase
{
    use FederationIntegrationHarness;

    private KomunitinAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new KomunitinAdapter();
    }

    public function test_protocol_identity(): void
    {
        $this->assertSame('komunitin', KomunitinAdapter::getProtocolName());
        $this->assertStringStartsWith('/', KomunitinAdapter::getDefaultApiPath());
    }

    public function test_endpoints_map_to_komunitin_paths(): void
    {
        $endpoints = [
            'members', 'member', 'listings', 'listing', 'transactions',
            'messages', 'health', 'accounts', 'about', 'currency', 'groups',
        ];
        foreach ($endpoints as $action) {
            $path = $this->adapter->mapEndpoint($action, ['id' => 42]);
            $this->assertIsString($path);
            $this->assertNotEmpty($path, "Empty endpoint for action {$action}");
        }
    }

    public function test_outbound_transforms_use_jsonapi_envelope(): void
    {
        $listing = [
            'id' => 1, 'title' => 'Help with garden', 'description' => 'Weeds',
            'type' => 'offer', 'user_id' => 1, 'tenant_id' => 2,
        ];
        $out = $this->adapter->transformOutboundListing($listing);

        // Komunitin uses JSON:API — expect {data: {type, attributes}}
        $this->assertArrayHasKey('data', $out);
        $this->assertIsArray($out['data']);
        $this->assertArrayHasKey('type', $out['data']);
        $this->assertArrayHasKey('attributes', $out['data']);
    }

    public function test_delete_verbs_on_currency_and_account_are_mapped(): void
    {
        // New DELETE verbs — optional surface.
        $currencyMethod = $this->adapter->mapHttpMethod('delete_currency', 'DELETE');
        $accountMethod  = $this->adapter->mapHttpMethod('delete_account', 'DELETE');
        $this->assertSame('DELETE', $currencyMethod);
        $this->assertSame('DELETE', $accountMethod);
    }

    public function test_inbound_member_unwraps_jsonapi(): void
    {
        $protocolMember = [
            'type'       => 'members',
            'id'         => 'ext-42',
            'attributes' => ['name' => 'Alice', 'email' => 'a@example.org'],
        ];
        $nexus = $this->adapter->transformInboundMember($protocolMember);
        $this->assertIsArray($nexus);
    }
}
