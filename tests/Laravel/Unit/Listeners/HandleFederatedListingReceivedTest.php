<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedListingReceived;
use App\Listeners\HandleFederatedListingReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Verifies HandleFederatedListingReceived (inbound federation listing listener).
 * Uses tenant id 99663 — unique to this file to avoid lock contention.
 */
class HandleFederatedListingReceivedTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99663;
    private const PARTNER_ID = 996631;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();

        // Insert the unique test tenant.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Fed Listing Test Tenant',
                'slug'       => 'fed-listing-99663',
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
     * Insert a shadow row into federation_listings and return its auto-id.
     */
    private function insertFederationListing(string $externalId = 'ext-1', string $title = 'Test Listing'): int
    {
        return (int) DB::table('federation_listings')->insertGetId([
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
            class_implements(HandleFederatedListingReceived::class) ?: []
        );
    }

    public function test_listener_queue_and_retry_config(): void
    {
        $ref      = new \ReflectionClass(HandleFederatedListingReceived::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $this->assertSame('federation', $ref->getProperty('queue')->getValue($instance));
        $this->assertSame(3, $ref->getProperty('tries')->getValue($instance));
        $this->assertSame([30, 120, 300], $ref->getProperty('backoff')->getValue($instance));
    }

    public function test_handle_logs_info_when_shadow_row_exists(): void
    {
        $localId = $this->insertFederationListing('ext-info-1', 'My Listing');

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $context) use ($localId) {
                return str_contains($msg, 'inbound listing persisted')
                    && $context['local_id']  === $localId
                    && $context['tenant_id'] === self::TENANT_ID;
            });

        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-info-1', 'title' => 'My Listing'],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        $this->assertTrue(true); // satisfied by Log assertions above
    }

    public function test_handle_logs_warning_and_returns_when_shadow_row_missing(): void
    {
        $missingId = 9999963;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($missingId) {
                return str_contains($msg, 'shadow row missing')
                    && $ctx['local_id'] === $missingId;
            });

        Log::shouldReceive('info')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $missingId,
            shadowRow:         ['external_id' => 'ghost'],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_when_tenant_not_found(): void
    {
        $unknownTenantId = 9996630;

        // TenantContext::setById() logs its own warning when the tenant is not found,
        // then returns false; the listener then logs its own 'tenant not found' warning.
        // Allow any number of warnings — we only care that no info is logged and the
        // listener's warning context contains the right tenant_id.
        $listenerWarningLogged = false;
        Log::shouldReceive('warning')
            ->atLeast()->once()
            ->withArgs(function (string $msg, ...$rest) use ($unknownTenantId, &$listenerWarningLogged) {
                if (str_contains($msg, 'tenant not found') && isset($rest[0]['tenant_id'])) {
                    if ($rest[0]['tenant_id'] === $unknownTenantId) {
                        $listenerWarningLogged = true;
                    }
                }
                return true; // accept all warnings
            });

        Log::shouldReceive('info')->never();

        $event    = new FederatedListingReceived(
            tenantId:          $unknownTenantId,
            externalPartnerId: self::PARTNER_ID,
            localId:           1,
            shadowRow:         [],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        $this->assertTrue($listenerWarningLogged, 'Listener must log a warning with the unknown tenant_id');
    }

    public function test_handle_is_idempotent_on_replay(): void
    {
        $localId = $this->insertFederationListing('ext-idempotent-1', 'Idempotent Listing');

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-idempotent-1'],
        );
        $listener = new HandleFederatedListingReceived();

        // Call twice — must not throw and must log twice (observability, not mutation)
        $listener->handle($event);
        $listener->handle($event);

        // The row is still there after two handle() calls
        $this->assertTrue(
            DB::table('federation_listings')
                ->where('id', $localId)
                ->where('tenant_id', self::TENANT_ID)
                ->exists()
        );
    }

    public function test_handle_reads_shadow_row_external_id_from_event_payload(): void
    {
        $localId = $this->insertFederationListing('ext-payload-check', 'Payload Listing');

        $capturedContext = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use (&$capturedContext) {
                $capturedContext = $ctx;
                return true;
            });
        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-payload-check', 'title' => 'Payload Listing'],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        $this->assertSame('ext-payload-check', $capturedContext['external_id'] ?? null);
        $this->assertSame('Payload Listing', $capturedContext['title'] ?? null);
    }

    public function test_handle_scopes_existence_check_to_correct_tenant(): void
    {
        // Insert row for a DIFFERENT tenant — handle() must NOT find it
        $otherTenantId = 99664; // neighbouring test file's tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            ['name' => 'Other', 'slug' => 'other-99664', 'is_active' => 1, 'depth' => 0,
             'features' => '{}', 'created_at' => now(), 'updated_at' => now()]
        );
        $foreignId = (int) DB::table('federation_listings')->insertGetId([
            'tenant_id'           => $otherTenantId,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => 'ext-foreign-1',
            'title'               => 'Foreign Listing',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Pass the foreign row's id but claim it belongs to OUR tenant — must warn
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg) {
                return str_contains($msg, 'shadow row missing');
            });
        Log::shouldReceive('info')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID, // wrong tenant for this id
            externalPartnerId: self::PARTNER_ID,
            localId:           $foreignId,
            shadowRow:         [],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_restores_tenant_context_after_run(): void
    {
        $localId = $this->insertFederationListing('ext-ctx-restore', 'Context Restore');

        // Pre-set a different tenant context to verify it is restored/reset
        TenantContext::setById(self::TENANT_ID);
        $priorId = TenantContext::currentId();

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-ctx-restore'],
        );
        $listener = new HandleFederatedListingReceived();
        $listener->handle($event);

        // In console (queue worker) mode, TenantContext is reset to null after a
        // scoped listener — asserting the behaviour that restoreAfterScopedListener
        // produces in a PHPUnit (console) process.
        $this->assertNull(TenantContext::currentId());
    }

    public function test_handle_does_not_send_any_http_requests(): void
    {
        $localId = $this->insertFederationListing('ext-no-http', 'No HTTP Listing');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-no-http'],
        );
        (new HandleFederatedListingReceived())->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_does_not_dispatch_any_queued_jobs(): void
    {
        $localId = $this->insertFederationListing('ext-no-queue', 'No Queue Listing');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-no-queue'],
        );
        (new HandleFederatedListingReceived())->handle($event);

        Queue::assertNothingPushed();
    }

    public function test_handle_does_not_mutate_the_shadow_row(): void
    {
        $localId = $this->insertFederationListing('ext-no-mutate', 'Immutable Listing');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $rowBefore = DB::table('federation_listings')
            ->where('id', $localId)
            ->first();

        $event    = new FederatedListingReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'ext-no-mutate', 'title' => 'Immutable Listing'],
        );
        (new HandleFederatedListingReceived())->handle($event);

        $rowAfter = DB::table('federation_listings')
            ->where('id', $localId)
            ->first();

        $this->assertEquals((array) $rowBefore, (array) $rowAfter);
    }
}
