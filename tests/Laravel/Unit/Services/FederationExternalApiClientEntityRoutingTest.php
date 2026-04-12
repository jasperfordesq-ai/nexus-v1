<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Services\FederationExternalApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Laravel\TestCase;

/**
 * Verifies the adapter-aware entity routing methods on FederationExternalApiClient.
 *
 * Each send* method must:
 *   - Resolve the protocol adapter for the partner
 *   - POST to the URL constructed from partner base_url + api_path + mapEndpoint(entity)
 *   - Send the payload produced by the adapter's transformOutbound<Entity>()
 */
class FederationExternalApiClientEntityRoutingTest extends TestCase
{
    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();
        FederationExternalApiClient::clearAdapterCache();
        Cache::flush();

        try {
            DB::table('federation_external_partner_logs')
                ->where('partner_id', '>=', 800000)->delete();
            DB::table('federation_external_partners')
                ->where('id', '>=', 800000)->delete();
        } catch (\Throwable $e) {
            // DB may not be available in pure unit runs
        }

        $this->partnerId = 800000 + random_int(1, 99999);
    }

    protected function tearDown(): void
    {
        try {
            DB::table('federation_external_partner_logs')
                ->where('partner_id', $this->partnerId)->delete();
            DB::table('federation_external_partners')
                ->where('id', $this->partnerId)->delete();
        } catch (\Throwable $e) {
            // ignore
        }

        Cache::flush();
        FederationExternalApiClient::clearAdapterCache();
        parent::tearDown();
    }

    private function seedPartner(string $protocolType = 'nexus'): bool
    {
        try {
            $encryptedApiKey = Crypt::encryptString('test-api-key');
        } catch (\Throwable $e) {
            return false;
        }

        $row = [
            'id'             => $this->partnerId,
            'tenant_id'      => $this->testTenantId,
            'name'           => 'Routing Partner',
            'base_url'       => 'https://partner.test',
            'api_path'       => '/api/v1',
            'api_key'        => $encryptedApiKey,
            'auth_method'    => 'api_key',
            'signing_secret' => '',
            'protocol_type'  => $protocolType,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        try {
            DB::table('federation_external_partners')->insert($row);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Data provider — one row per entity send method.
     *
     * @return array<string, array{0: string, 1: string, 2: array, 3: string}>
     *    [send method, expected endpoint path, sample payload, sample key that must appear in POST body]
     */
    public static function nexusEntityMatrix(): array
    {
        return [
            'listing' => ['sendListing',     '/listings',     ['id' => 1, 'title' => 'L', 'description' => 'D', 'type' => 'offer'], 'title'],
            'review'  => ['sendReview',      '/reviews',      ['id' => 1, 'rating' => 5, 'comment' => 'nice'], 'rating'],
            'event'   => ['sendEvent',       '/events',       ['id' => 1, 'title' => 'E', 'starts_at' => '2026-05-01T10:00:00Z'], 'title'],
            'group'   => ['sendGroup',       '/groups',       ['id' => 1, 'name' => 'G'], 'name'],
            'connection' => ['sendConnection', '/connections', ['id' => 1, 'requester_id' => 1, 'recipient_id' => 2, 'status' => 'pending'], 'status'],
            'vol'     => ['sendVolunteering', '/volunteering', ['id' => 1, 'title' => 'V'], 'title'],
            'member'  => ['sendMember',      '/members',      ['id' => 1, 'name' => 'N', 'email' => 'a@b.c'], 'name'],
        ];
    }

    /**
     * @dataProvider nexusEntityMatrix
     */
    public function test_nexus_send_methods_hit_correct_url_and_post_transformed_payload(string $method, string $expectedPath, array $payload, string $payloadKey): void
    {
        if (!$this->seedPartner('nexus')) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $result = FederationExternalApiClient::{$method}($this->partnerId, $payload);

        $this->assertTrue($result['success'], "{$method} should succeed");

        $expectedUrl = 'https://partner.test/api/v1' . $expectedPath;

        Http::assertSent(function ($request) use ($expectedUrl, $payloadKey) {
            if ($request->method() !== 'POST') {
                return false;
            }
            if ($request->url() !== $expectedUrl) {
                return false;
            }
            $body = json_decode($request->body(), true);
            if (!is_array($body)) {
                return false;
            }
            // Nexus pass-through preserves the key at the top level
            return array_key_exists($payloadKey, $body);
        });
    }

    public function test_komunitin_send_listing_emits_jsonapi_envelope(): void
    {
        if (!$this->seedPartner('komunitin')) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        FederationExternalApiClient::sendListing($this->partnerId, [
            'id' => 10,
            'title' => 'Hello',
            'description' => 'world',
            'type' => 'offer',
        ]);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            if (!str_ends_with($request->url(), '/offers')) {
                return false;
            }
            $body = json_decode($request->body(), true);
            return isset($body['data']['type'], $body['data']['attributes']['title'])
                && $body['data']['type'] === 'offers'
                && $body['data']['attributes']['title'] === 'Hello';
        });
    }

    public function test_credit_commons_send_event_emits_extension_envelope(): void
    {
        if (!$this->seedPartner('credit_commons')) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        FederationExternalApiClient::sendEvent($this->partnerId, [
            'id' => 1,
            'title' => 'CC-Event',
            'starts_at' => '2026-05-01T10:00:00Z',
        ]);

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/events')) {
                return false;
            }
            $body = json_decode($request->body(), true);
            return isset($body['type'])
                && $body['type'] === 'nexus_extension_event'
                && isset($body['payload']['title'])
                && $body['payload']['title'] === 'CC-Event';
        });
    }

    public function test_timeoverflow_send_listing_uses_posts_endpoint_with_post_wrapper(): void
    {
        if (!$this->seedPartner('timeoverflow')) {
            $this->markTestSkipped('DB/Crypt unavailable');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        FederationExternalApiClient::sendListing($this->partnerId, [
            'id' => 1,
            'title' => 'TO-Post',
            'type' => 'offer',
        ]);

        Http::assertSent(function ($request) {
            if (!str_ends_with($request->url(), '/posts')) {
                return false;
            }
            $body = json_decode($request->body(), true);
            return isset($body['post']['title']) && $body['post']['title'] === 'TO-Post';
        });
    }
}
