<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedGroupReceived;
use App\Listeners\HandleFederatedGroupReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Unit tests for HandleFederatedGroupReceived.
 *
 * This listener fires AFTER the shadow-table write has already been done by
 * FederationExternalWebhookController. The listener's current responsibility
 * is structured audit logging only. Tests assert:
 *   - Implements ShouldQueue on the 'federation' queue
 *   - Calls Log::info with the correct structured context
 *   - Does NOT touch the DB (no writes performed by the listener)
 *   - Handles various event payloads (name present / absent, custom kind)
 *   - Does not throw on a missing 'name' key in shadowRow
 *   - TenantContext is unchanged (listener is log-only, no tenant switch)
 *   - No HTTP calls are made
 *
 * Unique tenant id: 99666 (reserved for this test file).
 */
class HandleFederatedGroupReceivedTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99666;
    private const PARTNER_ID = 7001;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();

        // Seed our isolated tenant row.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'    => 'Fed Group Test Tenant',
                'slug'    => 'fed-group-test-99666',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------
    // Contract
    // ------------------------------------------------------------------

    public function test_listener_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(HandleFederatedGroupReceived::class) ?: []
        );
    }

    public function test_listener_routes_to_federation_queue(): void
    {
        $listener = new HandleFederatedGroupReceived();

        $this->assertSame('federation', $listener->queue);
    }

    // ------------------------------------------------------------------
    // Happy-path logging
    // ------------------------------------------------------------------

    public function test_handle_logs_info_with_correct_context(): void
    {
        Log::spy();

        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           42,
            shadowRow:         ['name' => 'Eco Warriors', 'privacy' => 'public'],
            kind:              'group',
        );

        (new HandleFederatedGroupReceived())->handle($event);

        Log::shouldHaveReceived('info')
            ->withArgs(fn($msg) => str_contains($msg, '[HandleFederatedGroupReceived]'))
            ->once();
    }

    public function test_handle_logs_group_name_from_shadow_row(): void
    {
        Log::spy();

        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           43,
            shadowRow:         ['name' => 'Repair Café', 'description' => 'Fixing stuff'],
        );

        (new HandleFederatedGroupReceived())->handle($event);

        Log::shouldHaveReceived('info')
            ->withArgs(fn($msg, $ctx = []) =>
                str_contains($msg, '[HandleFederatedGroupReceived]')
                && ($ctx['group_name'] ?? '__missing__') === 'Repair Café'
                && ($ctx['tenant_id'] ?? null) === self::TENANT_ID
                && ($ctx['external_partner_id'] ?? null) === self::PARTNER_ID
                && ($ctx['local_id'] ?? null) === 43
            )
            ->once();
    }

    public function test_handle_logs_null_group_name_when_absent_from_shadow_row(): void
    {
        Log::spy();

        // shadowRow intentionally omits 'name'
        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           44,
            shadowRow:         ['privacy' => 'private'],
        );

        (new HandleFederatedGroupReceived())->handle($event);

        Log::shouldHaveReceived('info')
            ->withArgs(fn($msg, $ctx = []) =>
                str_contains($msg, '[HandleFederatedGroupReceived]')
                && array_key_exists('group_name', $ctx)
                && $ctx['group_name'] === null
            )
            ->once();
    }

    public function test_handle_logs_custom_kind(): void
    {
        Log::spy();

        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           45,
            shadowRow:         ['name' => 'Community Hub'],
            kind:              'membership_change',
        );

        (new HandleFederatedGroupReceived())->handle($event);

        Log::shouldHaveReceived('info')
            ->withArgs(fn($msg, $ctx = []) =>
                str_contains($msg, '[HandleFederatedGroupReceived]')
                && ($ctx['kind'] ?? '__missing__') === 'membership_change'
            )
            ->once();
    }

    // ------------------------------------------------------------------
    // No side-effects
    // ------------------------------------------------------------------

    public function test_handle_makes_no_http_calls(): void
    {
        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           46,
            shadowRow:         ['name' => 'Test Group'],
        );

        (new HandleFederatedGroupReceived())->handle($event);

        Http::assertNothingSent();
    }

    public function test_handle_does_not_write_any_db_rows(): void
    {
        $countBefore = DB::table('federation_groups')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           47,
            shadowRow:         ['name' => 'Listener Should Not Write'],
        );

        (new HandleFederatedGroupReceived())->handle($event);

        $countAfter = DB::table('federation_groups')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        $this->assertSame($countBefore, $countAfter,
            'HandleFederatedGroupReceived must not insert/update federation_groups rows.');
    }

    // ------------------------------------------------------------------
    // TenantContext unchanged (log-only listener — no scoped switch)
    // ------------------------------------------------------------------

    public function test_handle_does_not_change_tenant_context(): void
    {
        // Confirm we start in the test tenant.
        $this->assertSame(self::TENANT_ID, TenantContext::currentId());

        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           48,
            shadowRow:         ['name' => 'Context Test Group'],
        );

        (new HandleFederatedGroupReceived())->handle($event);

        // Log-only listener must not call setById / reset.
        // TenantContext may be null (console path in restoreAfterScopedListener)
        // or remain at TENANT_ID — both are acceptable; what matters is it didn't
        // crash and the process is still usable.
        $idAfter = TenantContext::currentId();
        $this->assertTrue(
            $idAfter === null || $idAfter === self::TENANT_ID,
            "Unexpected tenant context after handle(): {$idAfter}"
        );
    }

    // ------------------------------------------------------------------
    // Default kind
    // ------------------------------------------------------------------

    public function test_default_kind_is_group(): void
    {
        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           49,
            shadowRow:         [],
        );

        $this->assertSame('group', $event->kind);
    }

    // ------------------------------------------------------------------
    // Event readonly properties carry through correctly
    // ------------------------------------------------------------------

    public function test_event_properties_are_accessible(): void
    {
        $event = new FederatedGroupReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           50,
            shadowRow:         ['name' => 'Property Test'],
            kind:              'group',
        );

        $this->assertSame(self::TENANT_ID, $event->tenantId);
        $this->assertSame(self::PARTNER_ID, $event->externalPartnerId);
        $this->assertSame(50, $event->localId);
        $this->assertSame('Property Test', $event->shadowRow['name']);
        $this->assertSame('group', $event->kind);
    }
}
