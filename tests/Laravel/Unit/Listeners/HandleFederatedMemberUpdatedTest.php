<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedMemberUpdated;
use App\Listeners\HandleFederatedMemberUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Verifies HandleFederatedMemberUpdated (inbound federation member listener).
 * Uses tenant id 99664 — unique to this file to avoid lock contention.
 */
class HandleFederatedMemberUpdatedTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99664;
    private const PARTNER_ID = 996641;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Fed Member Test Tenant',
                'slug'       => 'fed-member-99664',
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
     * Insert a shadow row into federation_members and return its auto-id.
     */
    private function insertFederationMember(string $externalId = 'mem-1', string $displayName = 'Alice'): int
    {
        return (int) DB::table('federation_members')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => $externalId,
            'display_name'        => $displayName,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    // ------------------------------------------------------------------ tests

    public function test_listener_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(HandleFederatedMemberUpdated::class) ?: []
        );
    }

    public function test_listener_queue_and_retry_config(): void
    {
        $ref      = new \ReflectionClass(HandleFederatedMemberUpdated::class);
        $instance = $ref->newInstanceWithoutConstructor();

        $this->assertSame('federation', $ref->getProperty('queue')->getValue($instance));
        $this->assertSame(3, $ref->getProperty('tries')->getValue($instance));
        $this->assertSame([30, 120, 300], $ref->getProperty('backoff')->getValue($instance));
    }

    public function test_handle_logs_info_when_shadow_row_exists(): void
    {
        $localId = $this->insertFederationMember('mem-info-1', 'Bob');

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($localId) {
                return str_contains($msg, 'inbound member update persisted')
                    && $ctx['local_id']  === $localId
                    && $ctx['tenant_id'] === self::TENANT_ID;
            });

        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-info-1', 'display_name' => 'Bob'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_when_shadow_row_missing(): void
    {
        $missingId = 9999964;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($missingId) {
                return str_contains($msg, 'shadow row missing')
                    && $ctx['local_id'] === $missingId;
            });

        Log::shouldReceive('info')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $missingId,
            shadowRow:         ['external_id' => 'ghost-mem'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_logs_warning_when_tenant_not_found(): void
    {
        $unknownTenantId = 9996640;

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

        $event    = new FederatedMemberUpdated(
            tenantId:          $unknownTenantId,
            externalPartnerId: self::PARTNER_ID,
            localId:           1,
            shadowRow:         [],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $this->assertTrue($listenerWarningLogged, 'Listener must log a warning with the unknown tenant_id');
    }

    public function test_handle_is_idempotent_on_replay(): void
    {
        $localId = $this->insertFederationMember('mem-idempotent-1', 'Carol');

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-idempotent-1'],
        );
        $listener = new HandleFederatedMemberUpdated();

        $listener->handle($event);
        $listener->handle($event);

        // Row must still exist after two handle() calls (listener is read-only)
        $this->assertTrue(
            DB::table('federation_members')
                ->where('id', $localId)
                ->where('tenant_id', self::TENANT_ID)
                ->exists()
        );
    }

    public function test_handle_includes_external_id_in_log_context(): void
    {
        $localId = $this->insertFederationMember('mem-ext-ctx', 'Dave');

        $capturedCtx = null;
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use (&$capturedCtx) {
                $capturedCtx = $ctx;
                return true;
            });
        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-ext-ctx'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $this->assertSame('mem-ext-ctx', $capturedCtx['external_id'] ?? null);
        $this->assertSame(self::PARTNER_ID, $capturedCtx['partner_id'] ?? null);
    }

    public function test_handle_scopes_existence_check_to_correct_tenant(): void
    {
        // Insert the row under a DIFFERENT tenant — our listener must NOT find it
        $otherTenantId = 99663; // neighbouring test file's tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => $otherTenantId],
            ['name' => 'Other', 'slug' => 'other-99663', 'is_active' => 1, 'depth' => 0,
             'features' => '{}', 'created_at' => now(), 'updated_at' => now()]
        );
        $foreignId = (int) DB::table('federation_members')->insertGetId([
            'tenant_id'           => $otherTenantId,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => 'mem-foreign-1',
            'display_name'        => 'Eve',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg) {
                return str_contains($msg, 'shadow row missing');
            });
        Log::shouldReceive('info')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID, // wrong tenant for this id
            externalPartnerId: self::PARTNER_ID,
            localId:           $foreignId,
            shadowRow:         [],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_restores_tenant_context_after_run(): void
    {
        $localId = $this->insertFederationMember('mem-ctx-restore', 'Frank');

        TenantContext::setById(self::TENANT_ID);

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-ctx-restore'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        // In console (queue) mode, restoreAfterScopedListener resets context to null.
        $this->assertNull(TenantContext::currentId());
    }

    public function test_handle_does_not_send_any_http_requests(): void
    {
        $localId = $this->insertFederationMember('mem-no-http', 'Grace');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-no-http'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_does_not_mutate_the_shadow_row(): void
    {
        $localId = $this->insertFederationMember('mem-no-mutate', 'Heidi');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $rowBefore = DB::table('federation_members')
            ->where('id', $localId)
            ->first();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-no-mutate'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        $rowAfter = DB::table('federation_members')
            ->where('id', $localId)
            ->first();

        $this->assertEquals((array) $rowBefore, (array) $rowAfter);
    }

    public function test_handle_does_not_dispatch_any_queued_jobs(): void
    {
        $localId = $this->insertFederationMember('mem-no-queue', 'Ivy');

        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->never();

        $event    = new FederatedMemberUpdated(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $localId,
            shadowRow:         ['external_id' => 'mem-no-queue'],
        );
        (new HandleFederatedMemberUpdated())->handle($event);

        Queue::assertNothingPushed();
    }
}
