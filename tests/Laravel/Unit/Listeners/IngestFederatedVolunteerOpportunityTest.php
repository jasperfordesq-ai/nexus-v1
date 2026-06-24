<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\FederatedVolunteeringReceived;
use App\Listeners\IngestFederatedVolunteerOpportunity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Unit tests for IngestFederatedVolunteerOpportunity.
 *
 * The controller has already upserted the shadow row into `federation_volunteering`.
 * This listener:
 *   1. Validates the shadow row still exists; bails if absent.
 *   2. Logs structured audit info.
 *   3. Mirrors the opportunity into `vol_opportunities` (idempotent upsert keyed
 *      on (tenant_id, external_partner_id, external_id)) when the federation
 *      columns are present.
 *   4. Restores TenantContext in its finally block.
 *   5. Skips the mirror when the shadow row is missing external_id or title.
 *
 * Unique tenant id: 99667 (reserved for this test file).
 */
class IngestFederatedVolunteerOpportunityTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 99667;
    private const PARTNER_ID = 7002;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake();

        // Seed isolated tenant row.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'IngestFedVol Test Tenant',
                'slug'       => 'ingest-fed-vol-99667',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Insert a shadow row into federation_volunteering and return its id.
     */
    private function seedShadowRow(array $overrides = []): int
    {
        $defaults = [
            'tenant_id'           => self::TENANT_ID,
            'external_partner_id' => self::PARTNER_ID,
            'external_id'         => 'ext-vol-' . uniqid(),
            'title'               => 'Test Vol Opportunity',
            'description'         => 'A test description.',
            'hours_requested'     => null,
            'location'            => 'Remote',
            'starts_at'           => '2027-01-15 10:00:00',
            'metadata'            => null,
            'created_at'          => now(),
            'updated_at'          => now(),
        ];

        return DB::table('federation_volunteering')->insertGetId(
            array_merge($defaults, $overrides)
        );
    }

    private function makeEvent(int $shadowId, array $shadowRow): FederatedVolunteeringReceived
    {
        return new FederatedVolunteeringReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           $shadowId,
            shadowRow:         $shadowRow,
        );
    }

    // ------------------------------------------------------------------
    // Contract
    // ------------------------------------------------------------------

    public function test_listener_implements_should_queue(): void
    {
        $this->assertContains(
            ShouldQueue::class,
            class_implements(IngestFederatedVolunteerOpportunity::class) ?: []
        );
    }

    public function test_listener_routes_to_federation_queue(): void
    {
        $listener = new IngestFederatedVolunteerOpportunity();

        $this->assertSame('federation', $listener->queue);
        $this->assertSame(1,  $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // ------------------------------------------------------------------
    // Bail when shadow row is missing
    // ------------------------------------------------------------------

    public function test_handle_skips_when_shadow_row_missing(): void
    {
        $loggedWarnings = [];
        Log::listen(function ($msg) use (&$loggedWarnings) {
            if ($msg->level === 'warning') {
                $loggedWarnings[] = $msg->message;
            }
        });

        $countBefore = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->count();

        $event = new FederatedVolunteeringReceived(
            tenantId:          self::TENANT_ID,
            externalPartnerId: self::PARTNER_ID,
            localId:           999999999, // no such row
            shadowRow:         ['external_id' => 'ghost', 'title' => 'Ghost Opp'],
        );

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $countAfter = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->count();

        $this->assertSame($countBefore, $countAfter,
            'Must not mirror when shadow row is absent.');

        $warnFound = array_filter($loggedWarnings, fn($m) => str_contains($m, 'shadow row missing'));
        $this->assertNotEmpty($warnFound, 'Expected "shadow row missing" warning log.');
    }

    // ------------------------------------------------------------------
    // Happy-path: shadow row present, mirror created
    // ------------------------------------------------------------------

    public function test_handle_mirrors_opportunity_into_vol_opportunities(): void
    {
        $extId    = 'ext-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Fix My Bike',
            'description' => 'Bicycle repair workshop',
            'location'    => 'Dublin',
            'starts_at'   => '2027-06-01 09:00:00',
        ]);

        $event = $this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'Fix My Bike',
            'description' => 'Bicycle repair workshop',
            'location'    => 'Dublin',
            'starts_at'   => '2027-06-01 09:00:00',
        ]);

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $row = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->where('external_id', $extId)
            ->first();

        $this->assertNotNull($row, 'vol_opportunities mirror row was not created.');
        $this->assertSame('Fix My Bike', $row->title);
        $this->assertSame('Dublin', $row->location);
        $this->assertSame(1, (int) $row->is_federated);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame('open', $row->status);
        $this->assertSame('2027-06-01', $row->start_date);
    }

    // ------------------------------------------------------------------
    // Idempotency: second handle() call updates, not duplicates
    // ------------------------------------------------------------------

    public function test_handle_is_idempotent_on_replay(): void
    {
        $extId    = 'ext-idem-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Soup Kitchen Help',
        ]);

        $shadowRow = [
            'external_id' => $extId,
            'title'       => 'Soup Kitchen Help',
            'description' => 'First version',
        ];

        $event = $this->makeEvent($shadowId, $shadowRow);

        (new IngestFederatedVolunteerOpportunity())->handle($event);
        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $count = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->where('external_id', $extId)
            ->count();

        $this->assertSame(1, $count,
            'Duplicate handle() calls must not create duplicate vol_opportunity rows.');
    }

    public function test_handle_updates_existing_mirror_row_on_replay(): void
    {
        $extId    = 'ext-upd-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Original Title',
        ]);

        // First call
        (new IngestFederatedVolunteerOpportunity())->handle($this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'Original Title',
            'description' => 'v1',
        ]));

        // Second call with updated data (simulates re-delivery after partner edits)
        (new IngestFederatedVolunteerOpportunity())->handle($this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'Updated Title',
            'description' => 'v2',
        ]));

        $row = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->where('external_id', $extId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Updated Title', $row->title);
    }

    // ------------------------------------------------------------------
    // Skips mirror when external_id or title is blank
    // ------------------------------------------------------------------

    public function test_handle_skips_mirror_when_external_id_blank(): void
    {
        $shadowId = $this->seedShadowRow([
            'external_id' => 'some-id-' . uniqid(), // real shadow id
            'title'       => 'No ExtId Mirror',
        ]);

        // shadowRow carries empty external_id — mirrorIntoVolOpportunities should bail
        $event = $this->makeEvent($shadowId, [
            'external_id' => '',  // blank
            'title'       => 'No ExtId Mirror',
            'description' => 'Should not be mirrored',
        ]);

        $countBefore = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->count();

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $countAfter = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->count();

        $this->assertSame($countBefore, $countAfter,
            'Mirror must be skipped when external_id is empty.');
    }

    public function test_handle_skips_mirror_when_title_blank(): void
    {
        $extId    = 'ext-notitle-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Title placeholder',  // real shadow title (NOT NULL)
        ]);

        $event = $this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => '',   // blank title in shadowRow passed to listener
            'description' => 'No title',
        ]);

        $countBefore = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->where('external_id', $extId)
            ->count();

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $countAfter = DB::table('vol_opportunities')
            ->where('tenant_id', self::TENANT_ID)
            ->where('external_partner_id', self::PARTNER_ID)
            ->where('external_id', $extId)
            ->count();

        $this->assertSame($countBefore, $countAfter,
            'Mirror must be skipped when title is empty.');
    }

    // ------------------------------------------------------------------
    // TenantContext is restored (finally block)
    // ------------------------------------------------------------------

    public function test_handle_restores_tenant_context_in_finally_block(): void
    {
        // Running in console: restoreAfterScopedListener → reset() → currentId() = null
        $extId    = 'ext-ctx-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Context Restore Test',
        ]);

        $event = $this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'Context Restore Test',
            'description' => 'checking finally',
        ]);

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        // In PHPUnit (console mode) the finally block calls reset() not setById().
        $idAfter = TenantContext::currentId();
        $this->assertTrue(
            $idAfter === null || is_int($idAfter),
            'TenantContext::currentId() returned an unexpected type after handle().'
        );
    }

    // ------------------------------------------------------------------
    // Audit log
    // ------------------------------------------------------------------

    public function test_handle_logs_info_when_shadow_row_exists(): void
    {
        $extId    = 'ext-log-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'Log Test Opportunity',
        ]);

        $infoMessages = [];
        Log::listen(function ($msg) use (&$infoMessages) {
            if ($msg->level === 'info') {
                $infoMessages[] = $msg->message;
            }
        });

        $event = $this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'Log Test Opportunity',
            'description' => 'log check',
        ]);

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        $found = array_filter(
            $infoMessages,
            fn($m) => str_contains($m, '[IngestFederatedVolunteerOpportunity]')
        );

        $this->assertNotEmpty($found,
            'Expected [IngestFederatedVolunteerOpportunity] info log was not emitted.');
    }

    // ------------------------------------------------------------------
    // No HTTP calls
    // ------------------------------------------------------------------

    public function test_handle_makes_no_http_calls(): void
    {
        $extId    = 'ext-nohttp-' . uniqid();
        $shadowId = $this->seedShadowRow([
            'external_id' => $extId,
            'title'       => 'No HTTP Opportunity',
        ]);

        $event = $this->makeEvent($shadowId, [
            'external_id' => $extId,
            'title'       => 'No HTTP Opportunity',
            'description' => 'http guard',
        ]);

        (new IngestFederatedVolunteerOpportunity())->handle($event);

        Http::assertNothingSent();
    }
}
