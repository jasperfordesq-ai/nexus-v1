<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedCommunityEventReceived;
use App\Listeners\HandleFederatedCommunityEventReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Verifies HandleFederatedCommunityEventReceived (inbound federation event listener).
 * Uses tenant id 99665 — unique to this file to avoid lock contention.
 */
class HandleFederatedCommunityEventReceivedTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99665;
    private const PARTNER_ID = 996651;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Fed Event Test Tenant',
                'slug'       => 'fed-event-99665',
                'is_active'  => 1,
                'depth'      => 0,
                'features'   => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Insert a shadow row into federation_events and return its auto-id.
     */
    private function insertFederationEvent(string $externalId = 'evt-1', string $title = 'Test Event'): int
    {
        return (int) DB::table('federation_events')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => $externalId,
            'title'               => $title,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    // ------------------------------------------------------------------ tests

    public function test_listener_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(HandleFederatedCommunityEventReceived::class) ?: []
        );
    }

    public function test_listener_queue_and_retry_config(): void
    {
        $ref      = new \ReflectionClass(HandleFederatedCommunityEventReceived::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $this->assertSame('federation', $ref->getProperty('queue')->getValue($instance));
        $this->assertSame(3, $ref->getProperty('tries')->getValue($instance));
        $this->assertSame([30, 120, 300], $ref->getProperty('backoff')->getValue($instance));
    }

    public function test_handle_logs_info_when_shadow_row_exists(): void
    {
        $localId = $this->insertFederationEvent('evt-info-1', 'Community Meetup');

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($localId) {
                return str_contains($msg, 'inbound event persisted')
                    && $ctx['local_id']  === $localId
                    && $ctx['tenant_id'] === self::TENANT_ID;
            });

        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-info-1', 'title' => 'Community Meetup'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_when_shadow_row_missing(): void
    {
        $missingId = 9999965;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($missingId) {
                return str_contains($msg, 'shadow row missing')
                    && $ctx['local_id'] === $missingId;
            });

        Log::shouldReceive('info')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $missingId,
            shadowRow:         ['external_id' => 'ghost-evt'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_when_tenant_not_found(): void
    {
        $unknownTenantId = 9996650;

        // TenantContext::setById() logs its own warning first, then returns false,
        // causing the listener to log its own 'tenant not found' warning.
        $listenerWarningLogged = false;
        Log::shouldReceive('warning')
            ->atLeast()->once()
            ->withArgs(function (string $msg, ...$rest) use ($unknownTenantId, &$listenerWarningLogged) {
                if (str_contains($msg, 'tenant not found') && isset($rest[0]['tenant_id'])) {
                    if ($rest[0]['tenant_id'] === $unknownTenantId) {
                        $listenerWarningLogged = true;
                    }
                }
                return true;
            });

        Log::shouldReceive('info')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          $unknownTenantId,
            externalPartnerId: self::PARTNER_ID,
            localId:           1,
            shadowRow:         [],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $this->assertTrue($listenerWarningLogged, 'Listener must log a warning with the unknown tenant_id');
    }

    public function test_handle_is_idempotent_on_replay(): void
    {
        $localId = $this->insertFederationEvent('evt-idempotent-1', 'Idempotent Event');

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-idempotent-1'],
        );
        $listener = new HandleFederatedCommunityEventReceived();

        $listener->handle($event);
        $listener->handle($event);

        // Row must still be present unchanged after two calls
        $this->assertTrue(
            DB::table('federation_events')
                ->where('id', $localId)
                ->where('tenant_id', self::TENANT_ID)
                ->exists()
        );
    }

    public function test_handle_logs_external_id_and_title_from_shadow_row(): void
    {
        $localId = $this->insertFederationEvent('evt-payload-1', 'Payload Event');

        $capturedCtx = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use (&$capturedCtx) {
                $capturedCtx = $ctx;
                return true;
            });
        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-payload-1', 'title' => 'Payload Event'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $this->assertSame('evt-payload-1', $capturedCtx['external_id'] ?? null);
        $this->assertSame('Payload Event', $capturedCtx['title'] ?? null);
    }

    public function test_handle_scopes_existence_check_to_correct_tenant(): void
    {
        // Row belongs to a different tenant — must NOT be found by our listener
        $otherTenantId = 99663;
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            ['name' => 'Other', 'slug' => 'other-99663b', 'is_active' => 1, 'depth' => 0,
             'features' => '{}', 'created_at' => now(), 'updated_at' => now()]
        );
        $foreignId = (int) DB::table('federation_events')->insertGetId([
            'tenant_id'           => $otherTenantId,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => 'evt-foreign-1',
            'title'               => 'Foreign Event',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg) {
                return str_contains($msg, 'shadow row missing');
            });
        Log::shouldReceive('info')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID, // wrong tenant for this id
            externalPartnerId: self::PARTNER_ID,
            localId:           $foreignId,
            shadowRow:         [],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_restores_tenant_context_after_run(): void
    {
        $localId = $this->insertFederationEvent('evt-ctx-restore', 'Context Event');

        TenantContext::setById(self::TENANT_ID);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-ctx-restore'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        // Console (queue) mode: restoreAfterScopedListener resets context to null
        $this->assertNull(TenantContext::currentId());
    }

    public function test_handle_does_not_send_any_http_requests(): void
    {
        $localId = $this->insertFederationEvent('evt-no-http', 'No HTTP Event');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-no-http'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_does_not_mutate_the_shadow_row(): void
    {
        $localId = $this->insertFederationEvent('evt-no-mutate', 'Immutable Event');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $rowBefore = DB::table('federation_events')
            ->where('id', $localId)
            ->first();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-no-mutate', 'title' => 'Immutable Event'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        $rowAfter = DB::table('federation_events')
            ->where('id', $localId)
            ->first();

        $this->assertEquals((array) $rowBefore, (array) $rowAfter);
    }

    public function test_handle_does_not_dispatch_any_queued_jobs(): void
    {
        $localId = $this->insertFederationEvent('evt-no-queue', 'No Queue Event');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedCommunityEventReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'evt-no-queue'],
        );
        (new HandleFederatedCommunityEventReceived())->handle($event);

        Queue::assertNothingPushed();
    }
}
