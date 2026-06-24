<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Services\CaringCommunity\CaringHourTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for caring:hour-transfers-retry console command.
 *
 * Uses unique tenant id 99707 for isolation.
 *
 * Strategy:
 *   - Tests that need controlled return values → mock CaringHourTransferService
 *     (avoids DNS validation inside OutboundUrlGuard for fake peer hostnames).
 *   - Tests that verify real DB state changes (inactive peer, skipped rows, etc.)
 *     → use real service with rows that never reach the HTTP layer.
 */
class RetryCaringHourTransferDeliveriesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99707;
    private const TENANT_SLUG = 'test-caring-retry-99707';
    private const PEER_SLUG   = 'peer-coop-99707';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Insert the isolation tenant with caring_community feature enabled.
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'Test Caring Retry Tenant',
            'slug'       => self::TENANT_SLUG,
            'is_active'  => 1,
            'features'   => json_encode(['caring_community' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        // Fake all outbound HTTP by default.
        Http::fake(['*' => Http::response(['accepted' => true], 200)]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Bind a mock CaringHourTransferService that returns controlled results.
     *
     * @param array{processed:int,delivered:int,failed:int,items:list<array<string,mixed>>} $result
     */
    private function mockService(array $result): void
    {
        $mock = Mockery::mock(CaringHourTransferService::class);
        $mock->shouldReceive('retryRemoteDeliveries')
            ->andReturn($result);
        $this->app->instance(CaringHourTransferService::class, $mock);
    }

    /** Insert a caring_federation_peers row. */
    private function insertFederationPeer(string $status = 'active'): void
    {
        DB::table('caring_federation_peers')->insertOrIgnore([
            'tenant_id'     => self::TENANT_ID,
            'peer_slug'     => self::PEER_SLUG,
            'display_name'  => 'Peer Cooperative',
            'base_url'      => 'https://peer-coop-99707.example.invalid',  // DNS won't resolve
            'shared_secret' => 'test-secret-99707',
            'status'        => $status,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Insert a caring_hour_transfers row (source, remote, sent).
     *
     * @param array<string,mixed> $overrides
     */
    private function insertSourceTransfer(array $overrides = []): int
    {
        $payload = json_encode([
            'source_tenant_slug'      => self::TENANT_SLUG,
            'destination_tenant_slug' => self::PEER_SLUG,
            'hours'                   => 2.00,
            'idempotency_key'         => uniqid('idem-', true),
        ]);

        $defaults = [
            'tenant_id'                     => self::TENANT_ID,
            'counterpart_tenant_slug'       => self::PEER_SLUG,
            'role'                          => 'source',
            'member_user_id'                => 1,
            'counterpart_member_email'      => 'member@peer.example.com',
            'hours_transferred'             => '2.00',
            'status'                        => 'sent',
            'is_remote'                     => 1,
            'payload_json'                  => $payload,
            'signature'                     => 'sig-test-99707',
            'remote_delivery_status'        => 'pending',
            'remote_delivery_attempts'      => 0,
            'remote_delivery_next_retry_at' => null,
            'remote_delivered_at'           => null,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ];

        return (int) DB::table('caring_hour_transfers')->insertGetId(
            array_merge($defaults, $overrides)
        );
    }

    // ------------------------------------------------------------------
    // Tests — command exit-code + output logic (mocked service)
    // ------------------------------------------------------------------

    public function test_exits_success_when_no_transfers_to_process(): void
    {
        $this->mockService(['processed' => 0, 'delivered' => 0, 'failed' => 0, 'items' => []]);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_exits_success_when_all_transfers_delivered(): void
    {
        $this->mockService(['processed' => 3, 'delivered' => 3, 'failed' => 0, 'items' => []]);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_exits_success_when_deliveries_partially_fail(): void
    {
        // Partial failure in delivery is NOT a tenant-level exception — exit 0.
        $this->mockService(['processed' => 3, 'delivered' => 1, 'failed' => 2, 'items' => []]);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);
    }

    public function test_exits_failure_when_service_throws_for_tenant(): void
    {
        $mock = Mockery::mock(CaringHourTransferService::class);
        $mock->shouldReceive('retryRemoteDeliveries')
            ->andThrow(new \RuntimeException('Simulated service failure'));
        $this->app->instance(CaringHourTransferService::class, $mock);

        // Need a transfer so the service is actually called.
        $this->insertFederationPeer();
        $this->insertSourceTransfer();

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(1);
    }

    public function test_output_includes_summary_counts(): void
    {
        $this->mockService(['processed' => 5, 'delivered' => 4, 'failed' => 1, 'items' => []]);

        // The summary line is:
        // "Remote hour-transfer retries: processed=5 delivered=4 failed=1 tenant_failures=0"
        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->expectsOutputToContain('Remote hour-transfer retries')
            ->assertExitCode(0);
    }

    public function test_limit_option_is_passed_to_service(): void
    {
        $capturedLimit = null;
        $mock = Mockery::mock(CaringHourTransferService::class);
        $mock->shouldReceive('retryRemoteDeliveries')
            ->once()
            ->with(self::TENANT_ID, 5)
            ->andReturnUsing(function (int $tid, int $lim) use (&$capturedLimit) {
                $capturedLimit = $lim;
                return ['processed' => 0, 'delivered' => 0, 'failed' => 0, 'items' => []];
            });
        $this->app->instance(CaringHourTransferService::class, $mock);

        $this->artisan('caring:hour-transfers-retry', [
            '--tenant' => self::TENANT_ID,
            '--limit'  => '5',
        ])->assertExitCode(0);

        $this->assertSame(5, $capturedLimit);
    }

    // ------------------------------------------------------------------
    // Tests — real service, DB state (no HTTP delivery triggered)
    // ------------------------------------------------------------------

    public function test_already_completed_transfer_is_not_picked_up(): void
    {
        $this->insertFederationPeer('active');

        // 'completed' status is not in the retry outbox filter.
        $id = $this->insertSourceTransfer([
            'status'                 => 'completed',
            'remote_delivery_status' => 'delivered',
        ]);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        // Row must be unchanged.
        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertSame('completed', $row->status);
        $this->assertSame('delivered', $row->remote_delivery_status);
    }

    public function test_destination_role_rows_are_not_retried(): void
    {
        $this->insertFederationPeer('active');

        $id = $this->insertSourceTransfer(['role' => 'destination']);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertSame('sent', $row->status);  // unchanged
        $this->assertSame(0, (int) $row->remote_delivery_attempts);
    }

    public function test_inactive_peer_causes_failure_record_without_http_call(): void
    {
        // Suspended peer → findByPeerSlug returns it but status !== 'active'
        // → recordRemoteDeliveryFailure is called, no HTTP.
        $this->insertFederationPeer('suspended');
        $id = $this->insertSourceTransfer();

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertSame('sent', $row->status);             // no funds moved
        $this->assertSame(1, (int) $row->remote_delivery_attempts);
        $this->assertNotNull($row->remote_delivery_next_retry_at);
    }

    public function test_missing_peer_causes_failure_record_without_http_call(): void
    {
        // No federation peer row → findByPeerSlug returns null.
        $id = $this->insertSourceTransfer();

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->remote_delivery_attempts);
    }

    public function test_future_retry_at_skips_row(): void
    {
        $this->insertFederationPeer('active');

        $id = $this->insertSourceTransfer([
            'remote_delivery_next_retry_at' => now()->addHours(2)->toDateTimeString(),
        ]);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        Http::assertNothingSent();

        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(0, (int) $row->remote_delivery_attempts);  // untouched
    }

    public function test_hours_amount_not_altered_after_failure_record(): void
    {
        // Suspended peer triggers failure path — verify decimal amount is preserved.
        $this->insertFederationPeer('suspended');
        $id = $this->insertSourceTransfer(['hours_transferred' => '3.50']);

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        $row = DB::table('caring_hour_transfers')->where('id', $id)->first();
        $this->assertEqualsWithDelta(3.50, (float) $row->hours_transferred, 0.001);
        $this->assertSame('sent', $row->status);  // no funds moved on failure
    }

    public function test_tenant_without_caring_feature_is_skipped(): void
    {
        // Disable the caring_community feature for this tenant.
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode(['caring_community' => false])]);

        $this->insertFederationPeer('active');
        $this->insertSourceTransfer();

        $this->artisan('caring:hour-transfers-retry', ['--tenant' => self::TENANT_ID])
            ->assertExitCode(0);

        // No delivery attempted.
        Http::assertNothingSent();
    }
}
