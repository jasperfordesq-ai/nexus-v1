<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\PartnerApi;

use App\Core\TenantContext;
use App\Services\PartnerApi\PartnerWebhookDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for PartnerWebhookDispatcher (AG60).
 *
 * Strategy:
 *   - createSubscription: happy path (secret returned, row inserted); private URL rejected.
 *   - dispatch: HMAC signature header present and correct; X-Event-Type header present;
 *               successful delivery resets failure_count + updates last_delivery_at;
 *               non-2xx response increments failure_count;
 *               exception path increments failure_count;
 *               non-matching event type skipped;
 *               wildcard event type matches everything;
 *               subscription with wrong tenant not dispatched;
 *               paused/failed subscriptions not dispatched.
 *   - listForPartner: returns rows scoped to the correct partner+tenant.
 *
 * Note: OutboundUrlGuard.isSafeHttpUrl does DNS resolution for hostnames,
 * so we use `https://203.0.113.1/` (TEST-NET-3, a real public IP, which
 * is NOT RFC1918) and rely on Http::fake() to intercept the HTTP call.
 * The guard will pass because 203.0.113.1 is a public IP.
 */
class PartnerWebhookDispatcherTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    // A public IP that OutboundUrlGuard allows and Http::fake() intercepts.
    private const SAFE_URL = 'https://203.0.113.1/webhook';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertPartner(): int
    {
        return (int) DB::table('api_partners')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'name'           => 'Test Partner ' . uniqid(),
            'slug'           => 'tp-' . uniqid(),
            'status'         => 'active',
            'is_sandbox'     => 1,
            'allowed_scopes' => json_encode([]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Insert a subscription directly, bypassing createSubscription's HTTPS check
     * so we can test dispatch behaviour with the safe test URL.
     */
    private function insertSubscription(
        int $partnerId,
        array $eventTypes = ['listing.created'],
        string $targetUrl = self::SAFE_URL,
        string $status = 'active',
        int $failureCount = 0,
    ): array {
        $secret = 'whsec_testfixture' . uniqid();
        $id = (int) DB::table('api_webhook_subscriptions')->insertGetId([
            'partner_id'   => $partnerId,
            'tenant_id'    => self::TENANT_ID,
            'event_types'  => json_encode($eventTypes),
            'target_url'   => $targetUrl,
            'secret'       => $secret,
            'status'       => $status,
            'failure_count' => $failureCount,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return ['id' => $id, 'secret' => $secret];
    }

    // ── createSubscription ────────────────────────────────────────────────────

    public function test_createSubscription_returns_secret_and_inserts_row(): void
    {
        $partnerId = $this->insertPartner();
        Http::fake(); // Guard does DNS; HTTP never fires but we want Http clean.

        $result = PartnerWebhookDispatcher::createSubscription(
            $partnerId,
            ['listing.created'],
            self::SAFE_URL,
        );

        $this->assertStringStartsWith('whsec_', $result['secret']);
        $this->assertSame(['listing.created'], $result['event_types']);

        $row = DB::table('api_webhook_subscriptions')->where('id', $result['id'])->first();
        $this->assertNotNull($row);
        $this->assertSame('active', $row->status);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
    }

    public function test_createSubscription_rejects_private_ip_target_url(): void
    {
        $partnerId = $this->insertPartner();
        $this->expectException(\InvalidArgumentException::class);

        PartnerWebhookDispatcher::createSubscription(
            $partnerId,
            ['listing.created'],
            'https://192.168.1.100/webhook', // RFC1918 — must be rejected
        );
    }

    public function test_createSubscription_rejects_http_url_when_https_required(): void
    {
        $partnerId = $this->insertPartner();
        $this->expectException(\InvalidArgumentException::class);

        PartnerWebhookDispatcher::createSubscription(
            $partnerId,
            ['listing.created'],
            'http://203.0.113.1/webhook', // HTTP — must be rejected
        );
    }

    // ── dispatch: HMAC signature ──────────────────────────────────────────────

    public function test_dispatch_sends_correct_hmac_sha256_signature_header(): void
    {
        $partnerId = $this->insertPartner();
        ['secret' => $secret] = $this->insertSubscription($partnerId);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', ['id' => 42]);

        Http::assertSent(function ($request) use ($secret) {
            $signature = $request->header('X-Signature')[0] ?? '';
            $body = $request->body();

            // Recompute expected HMAC.
            $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);

            return $signature === $expected;
        });
    }

    public function test_dispatch_sends_x_event_type_header(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertSubscription($partnerId, ['listing.created']);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', ['id' => 1]);

        Http::assertSent(function ($request) {
            return ($request->header('X-Event-Type')[0] ?? '') === 'listing.created';
        });
    }

    public function test_dispatch_payload_contains_event_and_data_fields(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertSubscription($partnerId, ['listing.created']);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', ['id' => 99, 'title' => 'Test']);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return ($body['event'] ?? '') === 'listing.created'
                && ($body['data']['id'] ?? null) === 99;
        });
    }

    // ── dispatch: success resets failure_count ────────────────────────────────

    public function test_dispatch_resets_failure_count_and_updates_last_delivery_at_on_success(): void
    {
        $partnerId = $this->insertPartner();
        ['id' => $subId] = $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'active', 3);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        $row = DB::table('api_webhook_subscriptions')->where('id', $subId)->first();
        $this->assertSame(0, (int) $row->failure_count);
        $this->assertNotNull($row->last_delivery_at);
    }

    // ── dispatch: non-2xx increments failure_count ────────────────────────────

    public function test_dispatch_increments_failure_count_on_non_2xx_response(): void
    {
        $partnerId = $this->insertPartner();
        ['id' => $subId] = $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'active', 0);

        Http::fake([self::SAFE_URL => Http::response('Bad Gateway', 502)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        $row = DB::table('api_webhook_subscriptions')->where('id', $subId)->first();
        $this->assertSame(1, (int) $row->failure_count);
        $this->assertNull($row->last_delivery_at);
    }

    // ── dispatch: exception increments failure_count ──────────────────────────

    public function test_dispatch_increments_failure_count_on_connection_exception(): void
    {
        $partnerId = $this->insertPartner();
        ['id' => $subId] = $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'active', 0);

        Http::fake([self::SAFE_URL => function () {
            throw new \RuntimeException('Connection refused');
        }]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        $row = DB::table('api_webhook_subscriptions')->where('id', $subId)->first();
        $this->assertSame(1, (int) $row->failure_count);
    }

    // ── dispatch: event type filtering ───────────────────────────────────────

    public function test_dispatch_skips_subscription_with_non_matching_event_type(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertSubscription($partnerId, ['member.joined']); // Does not match

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        Http::assertNothingSent();
    }

    public function test_dispatch_wildcard_event_type_matches_any_event(): void
    {
        $partnerId = $this->insertPartner();
        ['id' => $subId] = $this->insertSubscription($partnerId, ['*']);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('any.arbitrary.event', ['foo' => 'bar']);

        $row = DB::table('api_webhook_subscriptions')->where('id', $subId)->first();
        $this->assertNotNull($row->last_delivery_at, 'Wildcard subscription must receive any event');
    }

    // ── dispatch: tenant isolation ────────────────────────────────────────────

    public function test_dispatch_does_not_deliver_to_subscription_from_different_tenant(): void
    {
        $partnerId = $this->insertPartner();

        // Insert a subscription scoped to a DIFFERENT tenant.
        DB::table('api_webhook_subscriptions')->insert([
            'partner_id'   => $partnerId,
            'tenant_id'    => 999,
            'event_types'  => json_encode(['listing.created']),
            'target_url'   => self::SAFE_URL,
            'secret'       => 'whsec_other',
            'status'       => 'active',
            'failure_count' => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        // Dispatch in tenant 2 — should not fire for the tenant-999 row.
        PartnerWebhookDispatcher::dispatch('listing.created', []);

        Http::assertNothingSent();
    }

    // ── dispatch: paused/failed subscriptions skipped ────────────────────────

    public function test_dispatch_skips_paused_subscription(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'paused');

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        Http::assertNothingSent();
    }

    public function test_dispatch_skips_failed_subscription(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'failed', 25);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        Http::assertNothingSent();
    }

    // ── dispatch: partner-scoped dispatch ────────────────────────────────────

    public function test_dispatch_with_partner_id_only_delivers_to_that_partner(): void
    {
        $partnerA = $this->insertPartner();
        $partnerB = $this->insertPartner();
        ['id' => $subA] = $this->insertSubscription($partnerA, ['listing.created']);
        ['id' => $subB] = $this->insertSubscription($partnerB, ['listing.created']);

        Http::fake([self::SAFE_URL => Http::response('', 200)]);

        PartnerWebhookDispatcher::dispatch('listing.created', [], $partnerA);

        $rowA = DB::table('api_webhook_subscriptions')->where('id', $subA)->first();
        $rowB = DB::table('api_webhook_subscriptions')->where('id', $subB)->first();

        $this->assertNotNull($rowA->last_delivery_at, 'Partner A subscription must be delivered');
        $this->assertNull($rowB->last_delivery_at, 'Partner B subscription must NOT be delivered');
    }

    // ── listForPartner ────────────────────────────────────────────────────────

    public function test_listForPartner_returns_subscriptions_for_correct_partner(): void
    {
        $partnerA = $this->insertPartner();
        $partnerB = $this->insertPartner();
        $this->insertSubscription($partnerA, ['listing.created']);
        $this->insertSubscription($partnerA, ['member.joined']);
        $this->insertSubscription($partnerB, ['listing.created']); // should not appear

        $list = PartnerWebhookDispatcher::listForPartner($partnerA);

        $this->assertCount(2, $list);
        foreach ($list as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('event_types', $item);
            $this->assertArrayHasKey('target_url', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('failure_count', $item);
        }
    }

    // ── failure threshold auto-pause ─────────────────────────────────────────

    public function test_subscription_is_set_to_failed_after_25_failures(): void
    {
        $partnerId = $this->insertPartner();
        // Start at failure_count = 24; one more failure tips it over.
        ['id' => $subId] = $this->insertSubscription($partnerId, ['listing.created'], self::SAFE_URL, 'active', 24);

        Http::fake([self::SAFE_URL => Http::response('', 503)]);

        PartnerWebhookDispatcher::dispatch('listing.created', []);

        $row = DB::table('api_webhook_subscriptions')->where('id', $subId)->first();
        $this->assertSame(25, (int) $row->failure_count);
        $this->assertSame('failed', $row->status);
    }
}
